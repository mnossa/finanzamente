"""
Semantic Article Linker — FastAPI service
Calcola similarità semantica tra articoli magazine tramite sentence-transformers
per suggerire link interni rilevanti (basato sul contenuto, non solo sul titolo).
"""

from fastapi import FastAPI
from pydantic import BaseModel
from typing import List, Dict
import numpy as np
from sentence_transformers import SentenceTransformer
import re
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Semantic Article Linker", version="1.0.0")

MODEL_NAME = "paraphrase-multilingual-MiniLM-L12-v2"
logger.info(f"Caricamento modello {MODEL_NAME}...")
model = SentenceTransformer(MODEL_NAME)
logger.info("Modello caricato.")


# ---------- Modelli Pydantic ----------

class Article(BaseModel):
    id: int
    slug: str
    title: str
    text: str  # testo plain già privo di HTML/markdown


class BatchSuggestRequest(BaseModel):
    articles: List[Article]
    top_k: int = 5
    min_score: float = 0.45
    already_linked: Dict[str, List[str]] = {}  # source_id (stringa) → [target slugs]


class Suggestion(BaseModel):
    source_id: int
    target_id: int
    target_slug: str
    target_title: str
    score: float
    snippet: str


class BatchSuggestResponse(BaseModel):
    suggestions: List[Suggestion]
    articles_processed: int


# ---------- Helpers ----------

def clean_text(text: str, max_chars: int = 2000) -> str:
    """Pulisce il testo markdown e lo tronca per l'embedding."""
    text = re.sub(r'#{1,6}\s', '', text)
    text = re.sub(r'\*{1,2}([^*]+)\*{1,2}', r'\1', text)
    text = re.sub(r'\[([^\]]+)\]\([^\)]+\)', r'\1', text)
    text = re.sub(r'`[^`]+`', '', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text[:max_chars]


def split_sentences(text: str) -> List[str]:
    """Divide il testo in frasi (min 20 caratteri)."""
    sentences = re.split(r'(?<=[.!?])\s+', text)
    return [s.strip() for s in sentences if len(s.strip()) > 20]


def find_best_snippet(source_text: str, target_embedding: np.ndarray) -> str:
    """
    Trova la frase del testo sorgente semanticamente più vicina al target.
    Restituisce la frase come snippet di contesto per il suggerimento.
    """
    sentences = split_sentences(source_text)
    if not sentences:
        return source_text[:200]

    # Limita a 25 frasi per performance
    sentences = sentences[:25]
    sentence_embeddings = model.encode(sentences, normalize_embeddings=True, show_progress_bar=False)
    scores = sentence_embeddings @ target_embedding
    best_idx = int(np.argmax(scores))
    snippet = sentences[best_idx]

    if len(snippet) > 300:
        snippet = snippet[:300] + "…"

    return snippet


# ---------- Endpoints ----------

@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME}


@app.post("/batch-suggest", response_model=BatchSuggestResponse)
def batch_suggest(req: BatchSuggestRequest):
    if len(req.articles) < 2:
        return BatchSuggestResponse(suggestions=[], articles_processed=len(req.articles))

    # Testo da embeddare: titolo + contenuto (troncato)
    texts = [
        f"{art.title}. {clean_text(art.text)}"
        for art in req.articles
    ]

    logger.info(f"Calcolo embeddings per {len(texts)} articoli...")
    embeddings = model.encode(texts, normalize_embeddings=True, show_progress_bar=False)

    # Matrice di similarità coseno (NxN)
    sim_matrix = embeddings @ embeddings.T

    suggestions: List[Suggestion] = []

    for i, source_art in enumerate(req.articles):
        already = req.already_linked.get(str(source_art.id), [])

        scores = sim_matrix[i].copy()
        scores[i] = -1.0  # escludi auto-match

        sorted_indices = np.argsort(scores)[::-1]

        count = 0
        for j in sorted_indices:
            if count >= req.top_k:
                break

            score = float(scores[j])
            if score < req.min_score:
                break

            target_art = req.articles[j]

            if target_art.slug in already:
                continue

            snippet = find_best_snippet(
                clean_text(source_art.text, max_chars=3000),
                embeddings[j],
            )

            suggestions.append(Suggestion(
                source_id=source_art.id,
                target_id=target_art.id,
                target_slug=target_art.slug,
                target_title=target_art.title,
                score=round(score, 4),
                snippet=snippet,
            ))
            count += 1

    # Ordina globalmente per score discendente
    suggestions.sort(key=lambda s: s.score, reverse=True)

    logger.info(f"Suggerimenti generati: {len(suggestions)}")
    return BatchSuggestResponse(
        suggestions=suggestions,
        articles_processed=len(req.articles),
    )

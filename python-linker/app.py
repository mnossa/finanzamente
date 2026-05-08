"""
Semantic Article Linker — FastAPI service

Calcola similarità semantica tra articoli del magazine usando sentence-transformers
e suggerisce link interni rilevanti (basati sul contenuto, non solo sul titolo).

Strategia:
- chunking + mean-pooling: il modello multilingual-MiniLM ha max_seq_length=128 token,
  troncherebbe drasticamente articoli lunghi. Spezziamo il testo in chunk e facciamo
  la media degli embedding chunk per ottenere un embedding documento coerente con
  l'intero contenuto.
- pulizia markdown robusta (code fence, immagini, blockquote, liste, emoji, NFKC).
- segmentazione frasi in italiano via pysbd (gestisce abbreviazioni "art.", "S.p.A." ecc.).
- filtro near-duplicate (score >= max_score) per evitare suggerimenti su articoli
  sostanzialmente identici (es. "Guida 2025" vs "Guida 2026").
- cache embedding frasi del documento sorgente: encode una sola volta per articolo
  che produce suggerimenti, riusato per tutti i target.
"""

from __future__ import annotations

import logging
import re
import unicodedata
from typing import Dict, List, Optional, Tuple

import numpy as np
import pysbd
from fastapi import FastAPI
from pydantic import BaseModel, Field
from sentence_transformers import SentenceTransformer

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Semantic Article Linker", version="2.0.0")

MODEL_NAME = "paraphrase-multilingual-MiniLM-L12-v2"
# Limite hard del modello: oltre questo i token vengono troncati.
MODEL_MAX_TOKENS = 128
# Stima euristica: ~4 caratteri per token in italiano. Conservativo per non superare 128.
CHARS_PER_CHUNK = 380
MAX_CHUNKS_PER_DOC = 12

logger.info("Caricamento modello %s ...", MODEL_NAME)
model = SentenceTransformer(MODEL_NAME)
model.max_seq_length = MODEL_MAX_TOKENS
logger.info("Modello caricato (max_seq_length=%s).", model.max_seq_length)

# Segmenter italiano: gestisce abbreviazioni comuni (art., S.p.A., ecc.).
# clean=False per preservare il testo originale nello snippet.
sentence_segmenter = pysbd.Segmenter(language="it", clean=False)


# ---------- Modelli Pydantic ----------

class Article(BaseModel):
    id: int
    slug: str
    title: str
    text: str  # testo plain già privo di HTML/markdown lato chiamante


class BatchSuggestRequest(BaseModel):
    articles: List[Article] = Field(default_factory=list, max_length=2000)
    top_k: int = Field(default=5, ge=1, le=20)
    min_score: float = Field(default=0.55, ge=0.0, le=1.0)
    # Articoli con score >= max_score vengono scartati (probabili duplicati/draft).
    max_score: float = Field(default=0.95, ge=0.0, le=1.0)
    already_linked: Dict[str, List[str]] = Field(default_factory=dict)


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


# ---------- Pulizia markdown ----------

# Match emoji/simboli pittografici Unicode (BMP supplementare).
_EMOJI_RE = re.compile(
    "["
    "\U0001F300-\U0001FAFF"
    "\U0001F600-\U0001F64F"
    "\U0001F680-\U0001F6FF"
    "\U00002600-\U000027BF"
    "]"
)

_FENCED_CODE_RE = re.compile(r"```.*?```", re.DOTALL)
_INLINE_CODE_RE = re.compile(r"`[^`]+`")
_IMAGE_RE = re.compile(r"!\[[^\]]*\]\([^)]+\)")
_LINK_RE = re.compile(r"\[([^\]]+)\]\([^)]+\)")
_HTML_TAG_RE = re.compile(r"<[^>]+>")
_HEADING_RE = re.compile(r"^#{1,6}\s+", re.MULTILINE)
_BLOCKQUOTE_RE = re.compile(r"^>\s+", re.MULTILINE)
_LIST_BULLET_RE = re.compile(r"^[\s]*[-*+]\s+", re.MULTILINE)
_LIST_NUMBER_RE = re.compile(r"^[\s]*\d+\.\s+", re.MULTILINE)
_BOLD_ITALIC_AST_RE = re.compile(r"\*{1,3}([^*]+)\*{1,3}")
_BOLD_ITALIC_UND_RE = re.compile(r"_{1,3}([^_]+)_{1,3}")
_HORIZONTAL_RULE_RE = re.compile(r"^[\-*_]{3,}\s*$", re.MULTILINE)
_MULTI_WS_RE = re.compile(r"\s+")


def clean_text(text: str, max_chars: int = 8000) -> str:
    """Pulisce il markdown e normalizza il testo per l'embedding.

    Rimuove fence code multi-riga, immagini, link mantenendo l'anchor text,
    blockquote, liste, headings, grassetti/corsivi, regole orizzontali,
    emoji ed eventuali tag HTML residui. Normalizza Unicode (NFKC) e
    collassa whitespace. Tronca a `max_chars` solo come ultima difesa.
    """
    if not text:
        return ""

    text = unicodedata.normalize("NFKC", text)
    text = _FENCED_CODE_RE.sub(" ", text)
    text = _INLINE_CODE_RE.sub(" ", text)
    text = _IMAGE_RE.sub(" ", text)
    text = _LINK_RE.sub(r"\1", text)
    text = _HTML_TAG_RE.sub(" ", text)
    text = _HORIZONTAL_RULE_RE.sub(" ", text)
    text = _HEADING_RE.sub("", text)
    text = _BLOCKQUOTE_RE.sub("", text)
    text = _LIST_BULLET_RE.sub("", text)
    text = _LIST_NUMBER_RE.sub("", text)
    text = _BOLD_ITALIC_AST_RE.sub(r"\1", text)
    text = _BOLD_ITALIC_UND_RE.sub(r"\1", text)
    text = _EMOJI_RE.sub("", text)
    text = _MULTI_WS_RE.sub(" ", text).strip()

    if len(text) > max_chars:
        text = text[:max_chars]
    return text


# ---------- Chunking + embedding documento ----------

def chunk_text(text: str, chars_per_chunk: int = CHARS_PER_CHUNK,
               max_chunks: int = MAX_CHUNKS_PER_DOC) -> List[str]:
    """Spezza il testo in chunk preservando i confini di parola.

    Evita troncamenti a metà parola. Limita a max_chunks per cap performance
    su articoli molto lunghi (l'eccesso oltre quel limite contribuisce poco
    al segnale del documento dato il pooling).
    """
    if not text:
        return []
    if len(text) <= chars_per_chunk:
        return [text]

    chunks: List[str] = []
    cursor = 0
    n = len(text)
    while cursor < n and len(chunks) < max_chunks:
        end = min(cursor + chars_per_chunk, n)
        if end < n:
            # Cerca l'ultimo spazio entro la finestra per non rompere parole.
            space = text.rfind(" ", cursor, end)
            if space != -1 and space > cursor + chars_per_chunk // 2:
                end = space
        chunks.append(text[cursor:end].strip())
        cursor = end
    return [c for c in chunks if c]


def embed_document(title: str, text: str) -> np.ndarray:
    """Calcola un embedding documento mean-pooled e normalizzato.

    Il titolo viene anteposto al primo chunk per dare più peso al segnale
    del titolo (spesso più informativo del corpo nei primi caratteri).
    """
    cleaned = clean_text(text)
    chunks = chunk_text(cleaned)
    if not chunks:
        chunks = [title or " "]
    chunks[0] = f"{title}. {chunks[0]}" if title else chunks[0]

    embeddings = model.encode(
        chunks,
        normalize_embeddings=True,
        show_progress_bar=False,
    )
    pooled = np.asarray(embeddings).mean(axis=0)
    norm = float(np.linalg.norm(pooled))
    if norm > 0:
        pooled = pooled / norm
    return pooled


# ---------- Frasi e snippet ----------

def split_sentences(text: str, min_chars: int = 12, max_sentences: int = 25) -> List[str]:
    """Segmenta in frasi italiane via pysbd, filtra rumore e cap alla lunghezza."""
    if not text:
        return []
    raw = sentence_segmenter.segment(text)
    sentences = [s.strip() for s in raw if len(s.strip()) >= min_chars]
    return sentences[:max_sentences]


def encode_source_sentences(text: str) -> Tuple[List[str], Optional[np.ndarray]]:
    """Calcola una volta sola l'embedding delle frasi del documento sorgente.

    Restituisce (sentences, embeddings). embeddings=None se non ci sono frasi
    sufficientemente lunghe (es. articolo a bullet). In quel caso il chiamante
    farà fallback a un troncamento del testo.
    """
    cleaned = clean_text(text)
    sentences = split_sentences(cleaned)
    if not sentences:
        return [], None

    embeddings = model.encode(
        sentences,
        normalize_embeddings=True,
        show_progress_bar=False,
    )
    return sentences, np.asarray(embeddings)


def best_snippet(
    sentences: List[str],
    sentence_embeddings: Optional[np.ndarray],
    target_doc_embedding: np.ndarray,
    fallback_text: str,
) -> str:
    """Sceglie la frase del sorgente più affine al documento target."""
    if sentence_embeddings is None or len(sentences) == 0:
        snippet = fallback_text[:200]
    else:
        scores = sentence_embeddings @ target_doc_embedding
        best_idx = int(np.argmax(scores))
        snippet = sentences[best_idx]

    if len(snippet) > 300:
        snippet = snippet[:300] + "…"
    return snippet


# ---------- Endpoints ----------

@app.get("/health")
def health():
    return {
        "status": "ok",
        "model": MODEL_NAME,
        "max_seq_length": model.max_seq_length,
    }


@app.post("/batch-suggest", response_model=BatchSuggestResponse)
def batch_suggest(req: BatchSuggestRequest):
    if len(req.articles) < 2:
        return BatchSuggestResponse(suggestions=[], articles_processed=len(req.articles))

    if req.max_score <= req.min_score:
        # Configurazione incoerente: rilassiamo silenziosamente max_score per non
        # restituire zero risultati su una richiesta apparentemente valida.
        logger.warning(
            "max_score (%s) <= min_score (%s): forzo max_score=1.0",
            req.max_score, req.min_score,
        )
        effective_max_score = 1.0
    else:
        effective_max_score = req.max_score

    logger.info(
        "Calcolo embedding documento per %s articoli (chunking)...",
        len(req.articles),
    )
    doc_embeddings = np.vstack([
        embed_document(art.title, art.text) for art in req.articles
    ])

    # Matrice di similarità coseno (NxN) — embedding già normalizzati.
    sim_matrix = doc_embeddings @ doc_embeddings.T

    # Step 1: per ogni source decidi i target candidati (senza ancora calcolare snippet).
    candidates_per_source: Dict[int, List[Tuple[int, float]]] = {}
    for i, source_art in enumerate(req.articles):
        already = set(req.already_linked.get(str(source_art.id), []))

        scores = sim_matrix[i].copy()
        scores[i] = -1.0  # esclude auto-match

        sorted_indices = np.argsort(scores)[::-1]
        picks: List[Tuple[int, float]] = []

        for j in sorted_indices:
            if len(picks) >= req.top_k:
                break

            score = float(scores[j])
            if score < req.min_score:
                break  # i successivi sono ancora più bassi
            if score >= effective_max_score:
                # near-duplicate / articolo praticamente identico → skip
                continue

            target = req.articles[int(j)]
            if target.slug in already:
                continue

            picks.append((int(j), score))

        if picks:
            candidates_per_source[i] = picks

    # Step 2: encode frasi solo per i source che producono suggerimenti.
    snippet_cache: Dict[int, Tuple[List[str], Optional[np.ndarray], str]] = {}
    for i in candidates_per_source:
        sents, sent_emb = encode_source_sentences(req.articles[i].text)
        snippet_cache[i] = (sents, sent_emb, clean_text(req.articles[i].text))

    # Step 3: costruisci la lista finale.
    suggestions: List[Suggestion] = []
    for i, picks in candidates_per_source.items():
        source_art = req.articles[i]
        sents, sent_emb, fallback = snippet_cache[i]
        for j, score in picks:
            target = req.articles[j]
            snippet = best_snippet(sents, sent_emb, doc_embeddings[j], fallback)
            suggestions.append(Suggestion(
                source_id=source_art.id,
                target_id=target.id,
                target_slug=target.slug,
                target_title=target.title,
                score=round(score, 4),
                snippet=snippet,
            ))

    suggestions.sort(key=lambda s: s.score, reverse=True)

    logger.info(
        "Suggerimenti generati: %s (articoli=%s, min_score=%s, max_score=%s)",
        len(suggestions), len(req.articles), req.min_score, effective_max_score,
    )
    return BatchSuggestResponse(
        suggestions=suggestions,
        articles_processed=len(req.articles),
    )

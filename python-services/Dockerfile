FROM python:3.13-slim

WORKDIR /app

# Tutte le dipendenze (torch, numpy, scipy, scikit-learn, tokenizers, pydantic-core,
# safetensors, regex, ecc.) sono distribuite come wheel manylinux pre-compilate:
# nessun toolchain di build necessario. Saltando apt-get evitiamo flake da mirror
# Debian transitori in CI.

# Robustezza pip in CI: retry e timeout più alti.
ENV PIP_DEFAULT_TIMEOUT=120 \
    PIP_RETRIES=5

COPY requirements.txt .
RUN pip install --no-cache-dir --only-binary=:all: -r requirements.txt

# Pre-scarica il modello nell'immagine (evita download a runtime)
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')"

COPY main.py magazine_linker.py cohort_insights.py .

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=3 \
    CMD python -c "import urllib.request, sys; \
sys.exit(0 if urllib.request.urlopen('http://localhost:8000/health', timeout=5).getcode() == 200 else 1)"

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]

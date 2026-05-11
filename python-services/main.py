"""
Entrypoint FastAPI — servizi Python ausiliari (magazine, cohort insights, …).
"""

from __future__ import annotations

import logging

logging.basicConfig(level=logging.INFO)

from fastapi import FastAPI

from cohort_insights import router as cohort_router
from magazine_linker import router as magazine_router

app = FastAPI(title="Finanzamente Python services", version="1.0.0")
app.include_router(magazine_router)
app.include_router(cohort_router)

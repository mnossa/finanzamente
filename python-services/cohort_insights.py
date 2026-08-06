"""
Analisi cohort per notifiche in-app — solo numeri aggregati, nessun testo identificante.

Input: righe con subject_ref opaco, fascia reddito, macro-regione opzionale,
bucket percentuale Extra (multipli di 5 su 0–100).

Output: lista insight con codice e parametri whitelisted (fasce testuali).
"""

from __future__ import annotations

import logging
import statistics
from collections import defaultdict
from typing import DefaultDict, Dict, List, Optional, Tuple

from fastapi import APIRouter
from pydantic import BaseModel, Field, field_validator

logger = logging.getLogger(__name__)


class SnapshotRow(BaseModel):
    subject_ref: str = Field(min_length=8, max_length=64)
    income_band: str = Field(min_length=1, max_length=64)
    macro_region: Optional[str] = Field(default=None, max_length=64)
    wants_share_pct_bucket: int = Field(ge=0, le=100)

    @field_validator("wants_share_pct_bucket")
    @classmethod
    def multiple_of_five(cls, v: int) -> int:
        if v % 5 != 0:
            raise ValueError("wants_share_pct_bucket must be a multiple of 5")
        return v


class CohortAnalyzeRequest(BaseModel):
    snapshot_version: int = 1
    k_min: int = Field(default=50, ge=5, le=500_000)
    median_gap_pct_points: int = Field(default=15, ge=5, le=80)
    period: str = Field(min_length=7, max_length=7)
    rows: List[SnapshotRow] = Field(default_factory=list)


class InsightOut(BaseModel):
    subject_ref: str
    insight_code: str
    params: Dict[str, str]


class CohortAnalyzeResponse(BaseModel):
    insights: List[InsightOut]


def _format_diff_range(diff_pct_points: int) -> str:
    """Trasforma differenza (punti % sulla scala 0–100) in fascia tipo 15-25."""
    d = max(5, int(diff_pct_points))
    low = max(5, (d // 5) * 5 - 5)
    high = low + 10
    return f"{low}-{high}"


def _cohort_for_row(
    row: SnapshotRow,
    by_band_region: DefaultDict[Tuple[str, str], List[SnapshotRow]],
    by_band: DefaultDict[str, List[SnapshotRow]],
    k_min: int,
) -> Optional[List[SnapshotRow]]:
    band = row.income_band
    region = row.macro_region
    if region:
        key_br = (band, region)
        group = by_band_region.get(key_br) or []
        if len(group) >= k_min:
            return group
    whole = by_band.get(band) or []
    if len(whole) >= k_min:
        return whole
    return None


def analyze_cohort_insights(req: CohortAnalyzeRequest) -> CohortAnalyzeResponse:
    if not req.rows:
        return CohortAnalyzeResponse(insights=[])

    by_band_region: DefaultDict[Tuple[str, str], List[SnapshotRow]] = defaultdict(list)
    by_band: DefaultDict[str, List[SnapshotRow]] = defaultdict(list)

    for r in req.rows:
        by_band[r.income_band].append(r)
        if r.macro_region:
            by_band_region[(r.income_band, r.macro_region)].append(r)

    insights: List[InsightOut] = []

    for row in req.rows:
        cohort = _cohort_for_row(row, by_band_region, by_band, req.k_min)
        if cohort is None:
            continue

        buckets = [x.wants_share_pct_bucket for x in cohort]
        try:
            med = float(statistics.median(buckets))
        except statistics.StatisticsError:
            continue

        user_b = row.wants_share_pct_bucket
        diff = float(user_b) - med
        if diff < float(req.median_gap_pct_points):
            continue

        approx = _format_diff_range(int(round(diff)))
        insights.append(
            InsightOut(
                subject_ref=row.subject_ref,
                insight_code="cohort_wants_share_above_median",
                params={"approx_diff_range": approx},
            )
        )

    logger.info(
        "cohort-insights period=%s rows=%s insights=%s k_min=%s",
        req.period,
        len(req.rows),
        len(insights),
        req.k_min,
    )

    return CohortAnalyzeResponse(insights=insights)


router = APIRouter(tags=["cohort"])


@router.post("/cohort-insights/analyze", response_model=CohortAnalyzeResponse)
def cohort_insights_analyze_endpoint(req: CohortAnalyzeRequest) -> CohortAnalyzeResponse:
    """Insight statistici su cohort anonimi (nessun embedding / nessun testo libero)."""
    return analyze_cohort_insights(req)

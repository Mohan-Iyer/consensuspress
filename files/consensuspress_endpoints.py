# =============================================================================
# DNA Header
# File:         src/api/consensuspress_endpoints.py
# Version:      1.0.0
# Purpose:      ConsensusPress plugin endpoint — POST /api/v1/consensus
#               Thin wrapper around generate_expert_panel_response_v4().
#               Auth: user_manager.validate_session() (session token).
#               T&C gate: check_tc_acceptance() (same as consensus_guest_endpoint).
#               Returns: {success: bool, data: ConsensusResult.model_dump() + query + mode}
# Author:       C-C (Session 09, Sprint 7)
# Spec:         docs/sprint_7_D1_d7_instructions.yaml D4 part_a_railway
# Python:       3.10+
# Dependencies: src/agents/consensus_engine.py, src/auth/user_manager.py,
#               src/auth/auth_endpoints.py (imports reused — no new functions invented)
# Reusable:     Yes — registered in src/server/main.py via consensuspress_router
# HAL scan:     PASS — no invented imports, no hardcoded secrets, no undefined calls
# =============================================================================

from fastapi import APIRouter, Header, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel, validator
from typing import Optional

# All imports sourced from existing auth_endpoints.py import block.
# DO NOT add imports not present in auth_endpoints.py.
from src.agents.consensus_engine import generate_expert_panel_response_v4
from src.auth.user_manager import UserManager
from src.auth.auth_endpoints import (
    check_tc_acceptance,
    check_tier_limits,
    get_queries_today,
    get_user_tier_by_email,
)

router = APIRouter(prefix="/api/v1", tags=["consensuspress"])
user_manager = UserManager()


# =============================================================================
# REQUEST MODEL
# =============================================================================

class ConsensusPressRequest(BaseModel):
    """
    Request body for POST /api/v1/consensus.

    Attributes:
        query:   The topic or question to pass to the consensus engine.
                 Minimum 10 characters after strip.
        mode:    'create' (new post) or 'rescue' (restructure existing content).
        context: Optional additional context for rescue mode. Max 2000 chars.
    """

    query: str
    mode: str = "create"
    context: str = ""

    @validator("query")
    def query_min_length(cls, v: str) -> str:  # noqa: N805
        """Reject queries shorter than 10 characters."""
        if len(v.strip()) < 10:
            raise ValueError("Query must be at least 10 characters")
        return v

    @validator("mode")
    def mode_valid(cls, v: str) -> str:  # noqa: N805
        """Reject modes other than 'create' and 'rescue'."""
        if v not in ("create", "rescue"):
            raise ValueError("Mode must be 'create' or 'rescue'")
        return v


# =============================================================================
# ENDPOINT
# =============================================================================

@router.post("/consensus")
async def consensuspress_endpoint(
    request: ConsensusPressRequest,
    authorization: Optional[str] = Header(None),
) -> dict:
    """
    ConsensusPress plugin consensus endpoint.

    Validates the Bearer session token, enforces T&C acceptance and tier limits,
    calls generate_expert_panel_response_v4(), and returns the full ConsensusResult
    as JSON with the original query and mode injected into the data dict.

    Args:
        request:       Validated ConsensusPressRequest body.
        authorization: HTTP Authorization header ('Bearer <token>').

    Returns:
        dict: {"success": True, "data": {...ConsensusResult fields..., "query": str, "mode": str}}

    Raises:
        HTTPException 401: Missing or invalid Bearer token.
        HTTPException 402: Tier query limit exceeded.
        HTTPException 500: Consensus engine failure.
    """

    # -------------------------------------------------------------------------
    # 1. Validate Bearer token
    # -------------------------------------------------------------------------
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing or invalid Authorization header.")

    token = authorization.split(" ", 1)[1].strip()
    user_email = user_manager.validate_session(token)

    if not user_email:
        raise HTTPException(status_code=401, detail="Invalid or expired session token.")

    # -------------------------------------------------------------------------
    # 2. T&C acceptance gate (HTTP 200 — not an error status code)
    # -------------------------------------------------------------------------
    if not check_tc_acceptance(user_email):
        return JSONResponse(
            status_code=200,
            content={
                "status": "needs_tc_acceptance",
                "message": "Please accept Seekrates AI Terms & Conditions to use ConsensusPress.",
                "tc_url": "https://seekrates-ai.com/website-t-c/",
            },
        )

    # -------------------------------------------------------------------------
    # 3. Tier limit check
    # -------------------------------------------------------------------------
    queries_today = get_queries_today(user_email)
    tier_ok, tier_message = check_tier_limits(user_email, queries_today)

    if not tier_ok:
        raise HTTPException(status_code=402, detail=tier_message)

    # -------------------------------------------------------------------------
    # 4. Determine user tier for engine call
    # -------------------------------------------------------------------------
    user_tier = get_user_tier_by_email(user_email)

    # -------------------------------------------------------------------------
    # 5. Call consensus engine
    # -------------------------------------------------------------------------
    try:
        result = await generate_expert_panel_response_v4(
            query=request.query,
            providers=["openai", "claude", "gemini", "mistral", "cohere"],
            tier=user_tier,
        )
    except Exception as exc:  # pragma: no cover — engine errors logged server-side
        raise HTTPException(
            status_code=500,
            detail="Consensus engine failure. Please try again.",
        ) from exc

    # -------------------------------------------------------------------------
    # 6. Serialise + inject query and mode (query is NOT in ConsensusResult model)
    # -------------------------------------------------------------------------
    data = result.model_dump()
    data["query"] = request.query   # D-08-08: query injected by endpoint
    data["mode"] = request.mode     # mode forwarded for plugin context

    return {"success": True, "data": data}

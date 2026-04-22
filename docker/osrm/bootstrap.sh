#!/bin/bash
# First-run preprocessing for OSRM. Assumes the `osrm-data` init sidecar
# has already dropped the PBF extract at /data/${REGION}-latest.osm.pbf.
# On subsequent starts, cached .osrm files are reused and the server
# comes up immediately.
set -euo pipefail

REGION="${OSRM_REGION:-kansas}"
PROFILE="${OSRM_PROFILE:-/opt/car.lua}"
DATA_DIR="/data"
PBF="${DATA_DIR}/${REGION}-latest.osm.pbf"
OSRM_BASE="${DATA_DIR}/${REGION}-latest"

if [ ! -f "${OSRM_BASE}.osrm" ]; then
    if [ ! -f "${PBF}" ]; then
        echo "[osrm-bootstrap] ERROR: ${PBF} not found — the osrm-data init container should have downloaded it." >&2
        exit 1
    fi

    echo "[osrm-bootstrap] First-run preprocessing for region=${REGION}"
    echo "[osrm-bootstrap] osrm-extract (needs ~2 GB RAM for a state-sized extract)..."
    osrm-extract -p "${PROFILE}" "${PBF}"

    echo "[osrm-bootstrap] osrm-partition..."
    osrm-partition "${OSRM_BASE}.osrm"

    echo "[osrm-bootstrap] osrm-customize..."
    osrm-customize "${OSRM_BASE}.osrm"

    echo "[osrm-bootstrap] Preprocessing complete — data cached at ${OSRM_BASE}.osrm"
else
    echo "[osrm-bootstrap] Using cached data at ${OSRM_BASE}.osrm"
fi

echo "[osrm-bootstrap] Starting osrm-routed..."
exec osrm-routed --algorithm mld --port 5000 --max-table-size 10000 "${OSRM_BASE}.osrm"

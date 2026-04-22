#!/bin/bash
# First-run preprocessing for OSRM. On subsequent starts, cached .osrm files
# are reused and the server comes up immediately.
set -euo pipefail

REGION="${OSRM_REGION:-kansas}"
PBF_URL="${OSRM_PBF_URL:-https://download.geofabrik.de/north-america/us/${REGION}-latest.osm.pbf}"
PROFILE="${OSRM_PROFILE:-/opt/car.lua}"
DATA_DIR="/data"
PBF="${DATA_DIR}/${REGION}-latest.osm.pbf"
OSRM_BASE="${DATA_DIR}/${REGION}-latest"

if [ ! -f "${OSRM_BASE}.osrm" ]; then
    echo "[osrm-bootstrap] First-run preprocessing for region=${REGION}"

    if ! command -v curl >/dev/null 2>&1 && ! command -v wget >/dev/null 2>&1; then
        echo "[osrm-bootstrap] Installing wget..."
        apt-get update -q
        apt-get install -y -q --no-install-recommends wget ca-certificates
    fi

    if [ ! -f "${PBF}" ]; then
        echo "[osrm-bootstrap] Downloading ${PBF_URL}"
        if command -v curl >/dev/null 2>&1; then
            curl -fL --output "${PBF}" "${PBF_URL}"
        else
            wget -q --show-progress -O "${PBF}" "${PBF_URL}"
        fi
    fi

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

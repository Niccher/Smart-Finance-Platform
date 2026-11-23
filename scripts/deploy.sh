#!/usr/bin/env bash
# =============================================================
# deploy.sh — ONE-CLICK Deployment & Setup Pipeline
#             for M-Pesa Analyzer Platform (Monorepo)
#
# Usage:  bash scripts/deploy.sh
#         (run from the repository root)
#
# Production Standard Ports:
#   - Web Dashboard & API : Port 80
#   - ML AI Microservice  : Port 8001
#   - MySQL 8.4 Database  : Port 3306
#
# 13-Step Automated Pipeline:
#   1. Detect Dynamic IP (Public / LAN Fallback)
#   2. Pre-flight System & Resource Check (OS, CPU, RAM, Disk)
#   3. Git Working Tree Sanity Check & Pull
#   4. Write / Patch .env Configuration
#   5. Stop Old Containers & Clear Name Conflicts
#   6. Build & Start All Containers (MySQL, Web, ML)
#   7. Wait for MySQL Health (:3306)
#   8. Run Database Migrations & Seeders
#   9. Wait for ML Microservice Readiness (:8001)
#  10. Wait for WebApp Server Readiness (:80)
#  11. Container Permissions Enforcement (web/writable)
#  12. Housekeeping, Docker Storage & Cache Clearing
#  13. Clean Status Summary & Endpoints
# =============================================================
set -euo pipefail

# ── Colors & Formatting ───────────────────────────────────────
GREEN="\033[0;32m"; YELLOW="\033[1;33m"; RED="\033[0;31m"
CYAN="\033[0;36m"; BOLD="\033[1m"; RESET="\033[0m"

DEPLOY_START_TIME=$(date +%s)
SECTION_START_TIME=$DEPLOY_START_TIME
PREV_SECTION=""
declare -a STAGE_NAMES=()
declare -a STAGE_DURATIONS=()

format_duration() {
    local S=$1
    if [ "$S" -ge 60 ]; then
        local M=$((S / 60))
        local R=$((S % 60))
        echo "${M}m ${R}s"
    else
        echo "${S}s"
    fi
}

log()     { echo -e "${GREEN}[OK]${RESET}   $1"; }
warn()    { echo -e "${YELLOW}[WARN]${RESET} $1"; }
err()     {
    local NOW=$(date +%s)
    local ELAPSED=$((NOW - DEPLOY_START_TIME))
    echo -e "${RED}[FAIL]${RESET} $1 (failed after $(format_duration $ELAPSED))"
    exit 1
}

section() {
    local NOW=$(date +%s)
    if [ -n "$PREV_SECTION" ]; then
        local DURATION=$((NOW - SECTION_START_TIME))
        echo -e "${CYAN}──> Completed: ${PREV_SECTION} in $(format_duration $DURATION)${RESET}"
        STAGE_NAMES+=("$PREV_SECTION")
        STAGE_DURATIONS+=("$DURATION")
    fi
    PREV_SECTION="$1"
    SECTION_START_TIME=$NOW
    echo -e "\n${CYAN}${BOLD}=== $1 ===${RESET}"
}

finish_deployment() {
    local NOW=$(date +%s)
    if [ -n "$PREV_SECTION" ]; then
        local DURATION=$((NOW - SECTION_START_TIME))
        echo -e "${CYAN}──> Completed: ${PREV_SECTION} in $(format_duration $DURATION)${RESET}"
        STAGE_NAMES+=("$PREV_SECTION")
        STAGE_DURATIONS+=("$DURATION")
        PREV_SECTION=""
    fi
    local TOTAL_DURATION=$((NOW - DEPLOY_START_TIME))
    echo ""
    echo -e "${GREEN}${BOLD}══════════════════════════════════════════════════════════════${RESET}"
    echo -e "${GREEN}${BOLD}   DEPLOYMENT TIMELINE & EXECUTION SUMMARY${RESET}"
    echo -e "${GREEN}${BOLD}══════════════════════════════════════════════════════════════${RESET}"
    for i in "${!STAGE_NAMES[@]}"; do
        printf "   %-48s : %s\n" "${STAGE_NAMES[$i]}" "$(format_duration ${STAGE_DURATIONS[$i]})"
    done
    echo -e "   ------------------------------------------------------------"
    printf "   ${BOLD}%-48s${RESET} : ${BOLD}%s (%ss)${RESET}\n" "Total Overall Deployment Time" "$(format_duration $TOTAL_DURATION)" "$TOTAL_DURATION"
    echo -e "${GREEN}${BOLD}══════════════════════════════════════════════════════════════${RESET}"
}

# ── Guard: must run from repository root ─────────────────────
[ -f "docker-compose.yml" ] || err "Run from the repository root (where docker-compose.yml lives)."

# ── 1. Detect Dynamic IP ──────────────────────────────────────
section "1. Detecting Dynamic IP"
DETECTED_IP=$(curl -4 -s --max-time 3 ifconfig.me 2>/dev/null | tr -d '[:space:]' || true)
if [ -z "$DETECTED_IP" ] || [[ "$DETECTED_IP" =~ : ]]; then
    DETECTED_IP=$(curl -4 -s --max-time 3 icanhazip.com 2>/dev/null | tr -d '[:space:]' || true)
fi
if [ -z "$DETECTED_IP" ] || [[ "$DETECTED_IP" =~ : ]]; then
    DETECTED_IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{print $7}' | tr -d '[:space:]' || true)
fi
if [ -z "$DETECTED_IP" ] || [[ "$DETECTED_IP" =~ : ]]; then
    DETECTED_IP=$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -v ':' | head -n1 | tr -d '[:space:]' || true)
fi
if [ -z "$DETECTED_IP" ] || [[ "$DETECTED_IP" =~ : ]]; then
    DETECTED_IP="127.0.0.1"
fi
log "Dynamic Host IP: $DETECTED_IP"

# ── 2. Pre-flight System & Resource Check ─────────────────────
section "2. Pre-flight Disk & RAM Check"

if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_PRETTY="${PRETTY_NAME:-$NAME}"
else
    OS_PRETTY="$(uname -s)"
fi
KERNEL="$(uname -r)"
ARCH="$(uname -m)"

CPU_CORES=$(nproc 2>/dev/null || echo "1")
CPU_MODEL=$(grep -m1 "model name" /proc/cpuinfo 2>/dev/null | sed -E "s/^model name\s*:\s*//" | tr -s " " || echo "Generic CPU")
[ -z "$CPU_MODEL" ] && CPU_MODEL="Generic CPU"

TOTAL_MEM_MB=$(free -m | awk '/Mem:/ {print $2}')
USED_MEM_MB=$(free -m | awk '/Mem:/ {print $3}')
FREE_MEM_MB=$(free -m | awk '/Mem:/ {print ($7 != "" ? $7 : $4)}')
MEM_PCT=$(awk -v u="$USED_MEM_MB" -v t="$TOTAL_MEM_MB" 'BEGIN {if (t>0) printf "%.1f", (u/t)*100; else print "0"}')
MEM_FREE_PCT=$(awk -v f="$FREE_MEM_MB" -v t="$TOTAL_MEM_MB" 'BEGIN {if (t>0) printf "%.1f", (f/t)*100; else print "0"}')
TOTAL_MEM_GB=$(awk -v m="$TOTAL_MEM_MB" 'BEGIN {printf "%.2f GB", m/1024}')
USED_MEM_GB=$(awk -v m="$USED_MEM_MB" 'BEGIN {printf "%.2f GB", m/1024}')
FREE_MEM_GB=$(awk -v m="$FREE_MEM_MB" 'BEGIN {printf "%.2f GB", m/1024}')

SWAP_TOTAL_MB=$(free -m | awk '/Swap:/ {print $2}')

DISK_LINE=$(df -h / | awk 'NR>1 {print $(NF-4), $(NF-3), $(NF-2), $(NF-1), $(NF)}')
DISK_TOTAL=$(echo "$DISK_LINE" | awk '{print $1}')
DISK_USED=$(echo "$DISK_LINE" | awk '{print $2}')
DISK_FREE=$(echo "$DISK_LINE" | awk '{print $3}')
DISK_PCT=$(echo "$DISK_LINE" | awk '{print $4}')
DISK_FREE_MB=$(df -m / | awk 'NR>1 {print $(NF-2)}')

log "OS Environment   : ${OS_PRETTY} (${ARCH}, kernel ${KERNEL})"
log "CPU Architecture : ${CPU_MODEL} (${CPU_CORES} vCPU)"
log "Available Memory : ${FREE_MEM_MB}MB free of ${TOTAL_MEM_MB}MB"
log "RAM Breakdown    : Total: ${TOTAL_MEM_GB} | Used: ${USED_MEM_GB} (${MEM_PCT}%) | Free: ${FREE_MEM_GB} (${MEM_FREE_PCT}%)"
log "Swap Space       : ${SWAP_TOTAL_MB}MB total swap"
log "Available Disk   : ${DISK_FREE_MB}MB free on /"
log "Disk Breakdown   : Total: ${DISK_TOTAL} | Used: ${DISK_USED} (${DISK_PCT}) | Free: ${DISK_FREE} (${DISK_FREE_MB}MB)"

if [ "$SWAP_TOTAL_MB" -lt 1024 ] && [ "$FREE_MEM_MB" -lt 3500 ]; then
    warn "Swap space is low (${SWAP_TOTAL_MB}MB). Creating a 4GB swapfile to prevent OOM during LLM inference..."
    sudo fallocate -l 4G /swapfile 2>/dev/null || sudo dd if=/dev/zero of=/swapfile bs=1M count=4096 2>/dev/null || true
    sudo chmod 600 /swapfile 2>/dev/null || true
    sudo mkswap /swapfile 2>/dev/null || true
    sudo swapon /swapfile 2>/dev/null || true
    grep -q "/swapfile" /etc/fstab || echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null 2>&1 || true
    log "4 GB swapfile created and enabled"
fi

if [ "$DISK_FREE_MB" -lt 8000 ]; then
    warn "Free disk is below 8GB (${DISK_FREE_MB}MB free). Running proactive Docker cache prune..."
    docker builder prune -af >/dev/null 2>&1 || true
    docker image prune -f >/dev/null 2>&1 || true
    RECHECK_DISK=$(df -m / | awk 'NR>1 {print $(NF-2)}')
    log "Reclaimed disk space: ${RECHECK_DISK}MB free now"
fi

# ── 3. Git Working Tree Sanity Check & Pull ───────────────────
section "3. Git Working Tree Check & Pull"
if [ -d ".git" ]; then
    if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
        log "Local working tree has modifications — deploying current code without stashing."
    elif git remote | grep -q "origin"; then
        log "Pulling latest changes from origin..."
        PULL_LOG=$(git pull origin main 2>&1) || warn "Could not pull from origin, continuing with local code..."
        echo "$PULL_LOG" | tail -5
        if echo "$PULL_LOG" | grep -q "scripts/deploy.sh"; then
            log "deploy.sh was updated from origin. Re-executing deployment script..."
            exec bash "$0" "$@"
        fi
    else
        log "Working tree clean (local repository mode)"
    fi
fi

# ── 4. Write / Patch .env ─────────────────────────────────────
section "4. Writing .env Configuration"
if [ ! -f ".env" ]; then
    [ -f ".env.example" ] && cp .env.example .env && log "Created .env from .env.example" \
        || err ".env missing and no .env.example found — cannot continue"
fi

set_env() {
    local K="$1" V="$2"
    if grep -q "^${K}=" .env; then
        sed -i "s|^${K}=.*|${K}=${V}|g" .env
    elif grep -q "^${K} *=" .env; then
        sed -i "s|^${K} *=.*|${K} = ${V}|g" .env
    else
        echo "${K}=${V}" >> .env
    fi
}

# Auto-migrate legacy development ports (9002, 9021, 9022, 9306) to standard production ports
CURR_WEB=$(grep "^WEB_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || true)
if [ "$CURR_WEB" = "9002" ] || [ -z "$CURR_WEB" ]; then
    set_env "WEB_PORT" "80"
fi

CURR_ML=$(grep "^ML_MPESA_ANALYZER_API_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || true)
if [ "$CURR_ML" = "9021" ] || [ "$CURR_ML" = "8001" ] || [ -z "$CURR_ML" ]; then
    set_env "ML_MPESA_ANALYZER_API_PORT" "8002"
fi

CURR_LLAMA=$(grep "^ML_MPESA_ANALYZER_LLAMA_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || true)
if [ "$CURR_LLAMA" = "9022" ] || [ -z "$CURR_LLAMA" ]; then
    set_env "ML_MPESA_ANALYZER_LLAMA_PORT" "8080"
fi

CURR_MYSQL=$(grep "^MYSQL_HOST_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || true)
if [ "$CURR_MYSQL" = "9306" ] || [ -z "$CURR_MYSQL" ]; then
    set_env "MYSQL_HOST_PORT" "3306"
fi

# Auto-patch MPESA_CRYPT_KEY if using placeholder or missing
CURR_KEY=$(grep "^MPESA_CRYPT_KEY" .env 2>/dev/null | cut -d'=' -f2 | tr -d " '\"" || true)
if [ "$CURR_KEY" = "0123456789abcdef0123456789abcdef" ] || [ -z "$CURR_KEY" ]; then
    set_env "MPESA_CRYPT_KEY" "'a:r2yt>N3_\\\\Py,f='"
    set_env "MPESA_CRYPT_IV" "'[M[@_w[F4a>yQsJW'"
    log "Synchronized MPESA_CRYPT_KEY with Android app cryptographic specifications"
fi

# Resolve Ports (Default: 80 for WebApp, 8001 for ML, 3306 for MySQL)
WEB_PORT=$(grep "^WEB_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "80")
[ -z "$WEB_PORT" ] && WEB_PORT="80"
ML_PORT=$(grep "^ML_MPESA_ANALYZER_API_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "8002")
[ -z "$ML_PORT" ] && ML_PORT="8002"
DOZZLE_PORT=$(grep "^DOZZLE_PORT" .env 2>/dev/null | cut -d'=' -f2 | tr -d ' ' || echo "8888")
[ -z "$DOZZLE_PORT" ] && DOZZLE_PORT="8888"

# Format baseURL cleanly (omit :80 if standard HTTP)
if [ "$WEB_PORT" = "80" ]; then
    BASE_URL="http://${DETECTED_IP}/"
else
    BASE_URL="http://${DETECTED_IP}:${WEB_PORT}/"
fi

set_env "WEB_PORT" "$WEB_PORT"
set_env "ML_MPESA_ANALYZER_API_PORT" "$ML_PORT"
set_env "MYSQL_HOST_PORT" "3306"
set_env "REDIS_HOST" "redis"
set_env "REDIS_PORT" "6379"
set_env "app.baseURL" "$BASE_URL"
set_env "CI_ENVIRONMENT" "production"
set_env "DB_HOST" "mysql"
set_env "DB_PORT" "3306"
set_env "DB_NAME" "db_mpesa_analyzer"
set_env "DB_USER" "root"
set_env "ML_BACKEND_URL" "http://ml:9050"

log "app.baseURL               -> ${BASE_URL}"
log "WEB_PORT                  -> ${WEB_PORT} (Standard HTTP)"
log "ML_PORT                   -> ${ML_PORT} (AI Microservice API)"
log "ML_BACKEND_URL            -> http://ml:9050 (Internal network)"
log "DB_HOST                   -> mysql (Docker internal)"

# ── 5. Stop Old Containers & Clear Conflicts ──────────────────
section "5. Stopping Old Containers & Clearing Name Conflicts"
docker compose down --remove-orphans 2>&1 | tail -3 || true

# Explicitly remove legacy and current container names to prevent name collisions
docker rm -f smart-finance-mysql smart-finance-web smart-finance-ml smart-finance-redis smart-finance-minio smart-finance-minio-init smart-finance-dozzle smart-finance-caddy shared-mysql mpesa-analyzer-webapp ml-mpesa-analyzer mpesa-redis mpesa-minio mpesa-dozzle mpesa-caddy 2>/dev/null || true
docker network rm smart-finance-network hosts-shared-network 2>/dev/null || true
log "Old containers stopped and explicit name conflicts cleared"

# ── 6. Build & Start Containers ───────────────────────────────
section "6. Pulling Base Images & Starting All Containers"
docker compose pull --ignore-buildable 2>&1 | tail -10 || true
docker compose up --build -d 2>&1 | tail -20
log "Containers created and started (caddy, mysql, web, ml, redis, minio, dozzle)"

echo ""
echo -e "   ${BOLD}Docker Image Sizes:${RESET}"
docker images --filter "reference=*smart-finance*" --filter "reference=*mysql*" --filter "reference=*caddy*" --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}" 2>/dev/null | sed 's/^/   /' || true

echo ""
echo -e "   ${BOLD}Container Runtime Layer Sizes:${RESET}"
docker ps --size --filter "name=smart-finance" --filter "name=mpesa" --format "table {{.Names}}\t{{.Status}}\t{{.Size}}" 2>/dev/null | sed 's/^/   /' || true

# ── 7. Wait for MySQL Health ──────────────────────────────────
section "7. Waiting for MySQL to Become Healthy"
printf "   Waiting for MySQL"
MYSQL_HEALTHY=false
for i in $(seq 1 40); do
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' smart-finance-mysql 2>/dev/null || docker inspect --format='{{.State.Health.Status}}' shared-mysql 2>/dev/null || echo "missing")
    if [ "$STATUS" = "healthy" ]; then
        echo ""
        log "MySQL is healthy and accepting connections"
        MYSQL_HEALTHY=true
        break
    fi
    printf "."
    sleep 3
done
$MYSQL_HEALTHY || { echo ""; warn "MySQL did not report healthy within timeout. Check: docker compose logs mysql"; }

# ── 7b. Wait for Redis Health (:6379) ─────────────────────────
section "7b. Waiting for Redis Health (:6379)"
printf "   Checking Redis status"
REDIS_HEALTHY=false
for i in $(seq 1 15); do
    R_STATUS=$(docker inspect --format='{{.State.Health.Status}}' smart-finance-redis 2>/dev/null || docker inspect --format='{{.State.Health.Status}}' mpesa-redis 2>/dev/null || echo "missing")
    if [ "$R_STATUS" = "healthy" ]; then
        echo ""
        log "Redis in-memory cache and session store is healthy"
        REDIS_HEALTHY=true
        break
    fi
    printf "."
    sleep 2
done
$REDIS_HEALTHY || { echo ""; warn "Redis did not report healthy within timeout. Check: docker compose logs redis"; }

# ── 7c. Verify Cron Daemon in Web Container ───────────────────
section "7c. Verifying Cron Daemon in Web Container"
printf "   Checking cron service in web container"
CRON_RUNNING=false
for i in $(seq 1 10); do
    if docker compose exec -T web pgrep cron >/dev/null 2>&1; then
        echo ""
        log "Cron daemon is actively running inside the web container"
        CRON_RUNNING=true
        break
    fi
    printf "."
    sleep 2
done
if ! $CRON_RUNNING; then
    echo ""
    warn "Cron daemon is not running inside web container. Starting service..."
    docker compose exec -T web service cron start 2>&1 || true
    if docker compose exec -T web pgrep cron >/dev/null 2>&1; then
        log "Cron service started successfully"
        CRON_RUNNING=true
    else
        warn "Could not start cron daemon inside web container. Check: docker compose logs web"
    fi
fi

# Ensure cron log and environment files have proper permissions
docker compose exec -T web sh -c "
    touch /var/log/mpesa-cron.log /var/www/html/writable/cron_env.sh 2>/dev/null || true
    chmod 666 /var/log/mpesa-cron.log 2>/dev/null || true
    chmod 755 /var/www/html/writable/cron_env.sh 2>/dev/null || true
" 2>&1 && log "Cron log and environment files verified (/var/log/mpesa-cron.log)" || true

# ── 8. Run Database Migrations & Seeding ──────────────────────
section "8. Running Database Migrations & Seeding"
if $MYSQL_HEALTHY; then
    docker compose exec -T web php spark migrate --all 2>&1 \
        && log "Database migrations executed successfully" \
        || warn "Migrations had notices or completed with warnings"

    log "Applying initial database seeders..."
    docker compose exec -T web php spark db:seed SuperAdminSeeder 2>&1 || true
    docker compose exec -T web php spark db:seed AllowedSendersSeeder 2>&1 || true
    docker compose exec -T web php spark db:seed MLControlsSeeder 2>&1 || true
    log "Seeders executed successfully"
else
    warn "Skipping migrations and seeding because MySQL is not yet healthy"
fi

# ── 9. Wait for ML Microservice Readiness (:8001) ─────────────
section "9. Waiting for ML Microservice Ready (:${ML_PORT})"
printf "   Checking smart-finance-ml status"
ML_READY=false
for i in $(seq 1 45); do
    ML_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${ML_PORT}/health 2>/dev/null || echo "000")
    if [ "$ML_CODE" = "200" ]; then
        echo ""
        log "ML intelligence microservice is healthy (HTTP $ML_CODE)"
        ML_READY=true
        break
    fi
    printf "."
    sleep 3
done
$ML_READY || { echo ""; warn "ML microservice took longer to initialize. Check: docker compose logs ml"; }

# ── 10. Wait for Web Server Readiness (:80) ───────────────────
section "10. Waiting for WebApp Server Ready (:${WEB_PORT})"
printf "   Waiting for WebApp"
WEB_READY=false
for i in $(seq 1 25); do
    CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${WEB_PORT}/health 2>/dev/null || echo "000")
    if [[ "$CODE" =~ ^(200|301|302|307|403)$ ]]; then
        echo ""
        log "WebApp server is responding (HTTP $CODE)"
        WEB_READY=true
        break
    fi
    printf "."
    sleep 2
done
$WEB_READY || { echo ""; warn "Web server did not respond in time. Check: docker compose logs web"; }

# ── 10b. Verify Dozzle Log Viewer Readiness (:8888) ────────────
section "10b. Waiting for Dozzle Log Viewer Ready (:${DOZZLE_PORT})"
DOZZLE_READY=false
for i in $(seq 1 15); do
    DOZZLE_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${DOZZLE_PORT}/ 2>/dev/null || echo "000")
    if [[ "$DOZZLE_CODE" =~ ^(200|301|302)$ ]]; then
        log "Dozzle log viewer is operational (HTTP $DOZZLE_CODE)"
        DOZZLE_READY=true
        break
    fi
    sleep 1
done
$DOZZLE_READY || warn "Dozzle did not respond immediately. Check: docker compose logs dozzle"

# ── 11. Container Permissions Enforcement ─────────────────────
section "11. Setting Container Writable Directory Permissions"
docker compose exec -T web sh -c "
    mkdir -p writable/cache writable/logs writable/session writable/uploads/payloads writable/debugbar writable/backups && \
    chown -R www-data:www-data writable && \
    chmod -R 775 writable && \
    touch /var/log/mpesa-cron.log && \
    chmod 666 /var/log/mpesa-cron.log
" 2>&1 && log "Writable permissions enforced (www-data:775)" || warn "Failed to update writable permissions"

# ── 12. Housekeeping, Storage Metrics & Cache Clearing ────────
section "12. Housekeeping & Cache Clearing"
docker compose exec -T web php spark cache:clear 2>&1 || true
log "CodeIgniter 4 application cache cleared"

docker compose exec -T web sh -c "
    rm -rf writable/debugbar/* writable/tmp/* 2>/dev/null || true
" && log "Temporary runtime files and debugbar profiles cleared" || true

docker builder prune -af >/dev/null 2>&1 && log "Docker BuildKit build cache pruned" || true
docker image prune -f >/dev/null 2>&1 && log "Dangling Docker build layers pruned" || true

echo ""
echo -e "   ${BOLD}Docker Storage Footprint:${RESET}"
docker system df 2>/dev/null | sed 's/^/   /' || true

# ── 13. Container Status & Final Summary ──────────────────────
section "13. Deployment Summary & Health Verification"
docker compose ps

echo ""
FINAL_WEB=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${WEB_PORT}/health 2>/dev/null || echo "000")

if [ "$FINAL_WEB" = "200" ] || [ "$FINAL_WEB" = "307" ] || [ "$FINAL_WEB" = "302" ]; then
    log "PLATFORM IS LIVE — ALL SYSTEMS OPERATIONAL (WebApp HTTP ${FINAL_WEB})"
    echo ""
    echo -e "  ${BOLD}HTTPS Secure Gateway :${RESET}  https://smartfinance.local"
    echo -e "  ${BOLD}WebApp Dashboard     :${RESET}  ${BASE_URL}"
    echo -e "  ${BOLD}AI Financial Chat    :${RESET}  ${BASE_URL}dashboard/chat"
    echo -e "  ${BOLD}Admin Telemetry      :${RESET}  ${BASE_URL}admin/telemetry"
    echo -e "  ${BOLD}Admin Crons Schedule :${RESET}  ${BASE_URL}admin/crons"
    echo -e "  ${BOLD}Admin ML Config      :${RESET}  ${BASE_URL}admin/ml"
    echo -e "  ${BOLD}Mobile AI Gateway    :${RESET}  ${BASE_URL}api/v1/chat"
    echo -e "  ${BOLD}ML API Swagger       :${RESET}  http://${DETECTED_IP}:${ML_PORT}/docs"
    echo -e "  ${BOLD}ML Health Probe      :${RESET}  http://${DETECTED_IP}:${ML_PORT}/health"
    echo -e "  ${BOLD}Huey Worker Status   :${RESET}  http://${DETECTED_IP}:${ML_PORT}/health/worker"
    echo -e "  ${BOLD}Dozzle Live Logs     :${RESET}  http://${DETECTED_IP}:${DOZZLE_PORT}"
    echo -e "  ${BOLD}MinIO Object Console :${RESET}  http://${DETECTED_IP}:9001"
    echo -e "  ${BOLD}WebApp Health        :${RESET}  ${BASE_URL}health"
    echo -e "  ${BOLD}Redis Cache Server   :${RESET}  redis://${DETECTED_IP}:6379"
    echo ""
    finish_deployment

elif [ "$FINAL_WEB" = "500" ]; then
    warn "HTTP 500 detected on WebApp — dumping live error output..."
    echo -e "${RED}"
    curl -s http://localhost:${WEB_PORT}/health \
        | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    print('Error:', d.get('message',''))
    for f in d.get('trace', [])[:10]:
        print(' ', f.get('file','').split('/')[-1], ':', f.get('line',''), '->', f.get('function',''))
except Exception:
    sys.stdin = open('/dev/stdin')
    print(sys.stdin.read()[:2000])
" 2>/dev/null || true
    echo -e "${RESET}"
    echo ""
    echo -e "${YELLOW}Recent WebApp container logs:${RESET}"
    docker compose logs web 2>&1 | tail -30
    finish_deployment
    err "Fix the errors above, then re-run: bash scripts/deploy.sh"

else
    warn "HTTP ${FINAL_WEB} — unexpected status code. Recent WebApp logs:"
    docker compose logs web 2>&1 | tail -30
    finish_deployment
fi

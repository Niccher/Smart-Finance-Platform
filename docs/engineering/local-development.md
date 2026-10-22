# Local Development Guide (Native Host) — M-Pesa Analyzer Platform

Instructions for developing directly on your host development machine without running the application containers.

---

## 1. Prerequisites

- **PHP 8.4+** with extensions: `intl`, `mbstring`, `mysqli`, `curl`, `xml`, `zip`
- **Composer 2.x**
- **Python 3.12+** with `venv` and `pip`
- Running **MySQL 8.4** instance (or start only the database: `docker compose up -d mysql`)

---

## 2. WebApp Development (`web/`)

Navigate to the `web/` directory:
```bash
cd web
```

### Install PHP Dependencies
```bash
composer install
```

### Run Migrations & Seeders
```bash
php spark migrate --all
php spark db:seed SuperAdminSeeder
php spark db:seed AllowedSendersSeeder
php spark db:seed MLControlsSeeder
```

### Launch Development Server
```bash
php spark serve --host 0.0.0.0 --port 8000
```

---

## 3. ML Microservice Development (`ml/`)

Navigate to the `ml/` directory:
```bash
cd ml
```

### Create & Activate Python Virtual Environment
```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

### Start llama-server Binary
From within `ml/`:
```bash
export LD_LIBRARY_PATH="$(pwd)/llama-bin:$LD_LIBRARY_PATH"
./llama-bin/llama-server \
  --model models/qwen2.5-1.5b-instruct-q4_k_m.gguf \
  --port 8080 \
  --ctx-size 16384 \
  --batch-size 512
```

### Run FastAPI with Hot-Reload
In a new terminal (with virtual environment activated):
```bash
uvicorn app.main:app --host 0.0.0.0 --port 9050 --reload
```

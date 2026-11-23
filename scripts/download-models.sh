#!/usr/bin/env bash
set -euo pipefail

# Default model directory inside ml/
MODEL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/ml/models"
mkdir -p "$MODEL_DIR"

MODEL_FILE="$MODEL_DIR/qwen2.5-1.5b-instruct-q4_k_m.gguf"
MODEL_URL="${MODEL_DOWNLOAD_URL:-https://huggingface.co/Qwen/Qwen2.5-1.5B-Instruct-GGUF/resolve/main/qwen2.5-1.5b-instruct-q4_k_m.gguf}"

if [ -f "$MODEL_FILE" ]; then
    echo "Model already exists at: $MODEL_FILE"
    exit 0
fi

echo "Downloading Qwen2.5 1.5B Instruct GGUF into $MODEL_DIR..."
curl -L --retry 3 -o "$MODEL_FILE" "$MODEL_URL"
echo "Model downloaded successfully."

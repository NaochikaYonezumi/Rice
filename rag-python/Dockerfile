FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y \
    gcc \
    g++ \
    curl \
    # --- OCR / 画像 / PDF ラスタライズ用ランタイム ---
    # tesseract-ocr: 画像 → テキスト変換エンジン
    # tesseract-ocr-jpn / -jpn-vert: 日本語 (横書き / 縦書き) 学習データ
    # tesseract-ocr-eng: 英語学習データ
    # poppler-utils: pdf2image が PDF → 画像変換に使用 (pdftoppm)
    # libgl1 / libglib2.0-0: Pillow + OpenCV 系の依存共有ライブラリ
    tesseract-ocr \
    tesseract-ocr-jpn \
    tesseract-ocr-jpn-vert \
    tesseract-ocr-eng \
    poppler-utils \
    libgl1 \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# ビルド時に埋め込みモデルを pre-download しておく
# (実行時にネット越しの初回 DL を待たなくて済む)
ARG EMBED_MODEL=intfloat/multilingual-e5-small
ENV EMBED_MODEL=${EMBED_MODEL}
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('${EMBED_MODEL}')"

COPY . .

RUN mkdir -p /app/data

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]

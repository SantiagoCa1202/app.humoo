FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    TEMP_DIR=/tmp/humoo-beo \
    PYTHONPATH=/app/apps/beo-extractor

WORKDIR /app

RUN useradd --create-home --uid 10001 humoo \
    && mkdir -p /tmp/humoo-beo \
    && chown -R humoo:humoo /tmp/humoo-beo

COPY apps/beo-extractor/pyproject.toml /app/apps/beo-extractor/pyproject.toml
COPY apps/beo-extractor/app /app/apps/beo-extractor/app
COPY contracts /app/contracts

RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir 'PyMuPDF==1.26.3' 'jsonschema==4.25.1'

USER humoo

# Build from the repository root:
# docker build -f apps/beo-extractor/Dockerfile .
# Runtime requires HUMOO_API_BASE_URL, HUMOO_WORKER_ID and HUMOO_WORKER_TOKEN.
CMD ["python", "-m", "app.worker"]

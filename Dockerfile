FROM python:3.12-slim

WORKDIR /app

# Installe curl pour healthcheck
RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

# Copy pyproject.toml et installe les dépendances
COPY pyproject.toml .
RUN pip install --no-cache-dir fastapi uvicorn[standard] httpx pydantic pydantic-settings

# Copy le reste du code
COPY . .

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]

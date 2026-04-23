import os
import re
import logging
from typing import Optional

logger = logging.getLogger(__name__)

CHUNK_SIZE = 800
CHUNK_OVERLAP = 100


def _split_text(text: str, size: int = CHUNK_SIZE, overlap: int = CHUNK_OVERLAP) -> list[str]:
    words = text.split()
    chunks = []
    i = 0
    while i < len(words):
        chunk = " ".join(words[i : i + size])
        if chunk.strip():
            chunks.append(chunk.strip())
        i += size - overlap
    return chunks


async def scrape_url(url: str) -> list[str]:
    """
    URLからテキストを取得してチャンクに分割して返す。
    crawl4ai が使えればそちらを優先し、失敗時は requests + BeautifulSoup にフォールバック。
    """
    text = await _try_crawl4ai(url)
    if not text:
        text = _try_bs4(url)
    if not text:
        logger.warning(f"No content extracted from {url}")
        return []
    cleaned = _clean_text(text)
    return _split_text(cleaned)


async def _try_crawl4ai(url: str) -> Optional[str]:
    try:
        from crawl4ai import AsyncWebCrawler
        async with AsyncWebCrawler() as crawler:
            result = await crawler.arun(url=url)
            if result.success and result.markdown:
                return result.markdown
    except Exception as e:
        logger.debug(f"crawl4ai failed for {url}: {e}")
    return None


def _try_bs4(url: str) -> Optional[str]:
    try:
        import requests
        from bs4 import BeautifulSoup
        headers = {"User-Agent": "Mozilla/5.0 (compatible; RAGBot/1.0)"}
        resp = requests.get(url, headers=headers, timeout=15)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "html.parser")
        for tag in soup(["script", "style", "nav", "footer", "header"]):
            tag.decompose()
        return soup.get_text(separator="\n")
    except Exception as e:
        logger.debug(f"bs4 failed for {url}: {e}")
    return None


def _clean_text(text: str) -> str:
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
    return text.strip()

import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
import time
import logging

logger = logging.getLogger(__name__)


class KnowledgeScraper:
    """ベースURL配下の内部リンクを BFS で巡回するシンプルなスクレイパ。

    - max_depth で深さを制限
    - max_pages で総取得件数を制限 (default 30)
    - sleep_sec でリクエスト間隔を制御
    """

    def __init__(
        self,
        base_url: str,
        max_pages: int = 30,
        sleep_sec: float = 0.3,
        request_timeout: int = 15,
    ):
        self.base_url = base_url
        self.domain = urlparse(base_url).netloc
        self.visited: set[str] = set()
        self.data: list[dict] = []
        self.max_pages = max_pages
        self.sleep_sec = sleep_sec
        self.request_timeout = request_timeout

    def is_internal(self, url: str) -> bool:
        return urlparse(url).netloc == self.domain

    def _should_skip(self, url: str) -> bool:
        """ファイル拡張子などスクレイピング対象外のURLを除外する。"""
        lower = url.lower().split('?')[0]
        skip_exts = (
            '.pdf', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.ico',
            '.zip', '.tar', '.gz', '.7z', '.rar',
            '.mp4', '.mp3', '.avi', '.mov',
            '.css', '.js', '.json', '.xml',
            '.exe', '.dmg', '.msi',
        )
        return lower.endswith(skip_exts)

    def scrape(self, url: str | None = None, depth: int = 0, max_depth: int = 2) -> None:
        """ベース URL から幅優先で巡回する (再帰呼び出し版)。"""
        if url is None:
            url = self.base_url

        # 上限到達したら打ち切り
        if len(self.data) >= self.max_pages:
            return
        if url in self.visited or depth > max_depth:
            return
        if self._should_skip(url):
            return

        self.visited.add(url)

        try:
            response = requests.get(url, timeout=self.request_timeout, headers={
                'User-Agent': 'Mozilla/5.0 (compatible; RiceKnowledgeBot/1.0)'
            })
            if response.status_code != 200:
                logger.info(f"skip {url} (status {response.status_code})")
                return

            content_type = response.headers.get('Content-Type', '')
            if 'html' not in content_type.lower():
                return

            soup = BeautifulSoup(response.text, 'html.parser')

            # コンテンツの抽出 (スクリプトやスタイルを除外)
            for tag in soup(["script", "style", "noscript"]):
                tag.decompose()

            text = soup.get_text(separator=' ', strip=True)
            self.data.append({
                "url": url,
                "base_url": self.base_url,
                "content": text,
                "title": (soup.title.string if soup.title else url) or url,
            })
            logger.info(f"scraped [{len(self.data)}/{self.max_pages}] {url}")

            # 再帰的にリンクを探索
            if depth < max_depth and len(self.data) < self.max_pages:
                for link in soup.find_all('a', href=True):
                    if len(self.data) >= self.max_pages:
                        break
                    next_url = urljoin(url, link['href'])
                    # フラグメントの除去
                    next_url = next_url.split('#')[0]
                    # クエリ除去 (動的 URL 増殖を防ぐ)
                    next_url = next_url.split('?')[0]
                    if not next_url or next_url in self.visited:
                        continue
                    if not self.is_internal(next_url):
                        continue
                    if self._should_skip(next_url):
                        continue
                    self.scrape(next_url, depth + 1, max_depth)
                    if self.sleep_sec > 0:
                        time.sleep(self.sleep_sec)

        except requests.exceptions.Timeout:
            logger.warning(f"timeout {url}")
        except Exception as e:
            logger.warning(f"error scraping {url}: {e}")

    def get_results(self) -> list[dict]:
        return self.data

import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
import time

class KnowledgeScraper:
    def __init__(self, base_url):
        self.base_url = base_url
        self.domain = urlparse(base_url).netloc
        self.visited = set()
        self.data = []

    def is_internal(self, url):
        return urlparse(url).netloc == self.domain

    def scrape(self, url=None, depth=0, max_depth=2):
        if url is None:
            url = self.base_url

        if url in self.visited or depth > max_depth:
            return
        
        print(f"Scraping: {url}")
        self.visited.add(url)

        try:
            response = requests.get(url, timeout=10)
            if response.status_code != 200:
                return

            soup = BeautifulSoup(response.text, 'html.parser')
            
            # コンテンツの抽出 (スクリプトやスタイルを除外)
            for script in soup(["script", "style"]):
                script.decompose()
            
            text = soup.get_text(separator=' ', strip=True)
            self.data.append({
                "url": url,
                "content": text,
                "title": soup.title.string if soup.title else url
            })

            # 再帰的にリンクを探索
            if depth < max_depth:
                for link in soup.find_all('a', href=True):
                    next_url = urljoin(url, link['href'])
                    # フラグメントの除去
                    next_url = next_url.split('#')[0]
                    if self.is_internal(next_url):
                        self.scrape(next_url, depth + 1, max_depth)
                        time.sleep(1) # 負荷軽減

        except Exception as e:
            print(f"Error scraping {url}: {e}")

    def get_results(self):
        return self.data

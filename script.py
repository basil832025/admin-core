import requests
from bs4 import BeautifulSoup
import xml.etree.ElementTree as ET
from xml.dom.minidom import parseString
from datetime import datetime
from pathlib import Path
from urllib.parse import urljoin

from datetime import datetime, timezone

FEED_VERSION = datetime.now(timezone.utc).strftime("%Y%m%d")  # змінюється раз на день

BASE_URL = "https://3piroga.ua"
FEED_FILE = "feed_ua.xml"

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
    "Referer": "https://3piroga.ua",
}

# Абсолютний шлях до директорії, де зберігатиметься XML-файл
OUTPUT_DIR = Path("/home/a3piroga/3piroga.ua/www")

# Google product category (ID) для випічки (Baked Goods)
GOOGLE_PRODUCT_CATEGORY_ID = "5408"

# Твоя ієрархія (для зручних фільтрів/біддінгу в Ads)
PRODUCT_TYPE = "Їжа > Випічка > Пироги"

def fetch_products():
    response = requests.get(BASE_URL, headers=HEADERS, timeout=30)

    if response.status_code != 200:
        print(f"Помилка отримання сторінки: {response.status_code}")
        return []

    soup = BeautifulSoup(response.text, "lxml")
    products = []

    for item in soup.select("div[itemtype='http://schema.org/Product']"):
        name_el = item.select_one("div[itemprop='name'] a")
        a_el = item.select_one("a")
        img_el = item.select_one("meta[itemprop='image']")
        desc_el = item.select_one("meta[itemprop='description']")

        # Захист від "битих" блоків
        if not (name_el and a_el and img_el and desc_el):
            continue

        title = name_el.text.strip()
        link = urljoin(BASE_URL + "/", a_el.get("href", ""))
        image_link = urljoin(BASE_URL + "/", img_el.get("content", ""))
        # Примусова інвалідація кешу Merchant по картинках
        image_link = f"{image_link}?v={FEED_VERSION}"
        description = desc_el.get("content", "").strip()

        sizes = []
        for size in item.select("li[data-price]"):
            v = size.select_one(".item_value")
            t = size.select_one(".item_txt")
            w = size.select_one(".inline_part")
            if not (v and t and w):
                continue

            size_text = f"{v.text.strip()} {t.text.strip()}"
            weight_text = w.text.strip()

            price_raw = size.get("data-price", "").strip()
            try:
                price_float = float(price_raw)
            except ValueError:
                continue

            sizes.append({
                "size": size_text,
                "weight": weight_text,
                "price": price_float
            })

        if not sizes:
            continue

        min_price = min(sizes, key=lambda x: x["price"])

        slug = link.rstrip("/").split("/")[-1]
        prod_id = f"ua_{slug}"[:50]

        products.append({
            "id": prod_id,
            "title": title,
            "link": link,
            "image_link": image_link,
            "description": description,
            "brand": "3piroga",
            "condition": "new",
            "availability": "in stock",
            "price": f"{min_price['price']:.2f} UAH",
            "size": min_price["size"],
            "weight": min_price["weight"],
            "google_product_category": GOOGLE_PRODUCT_CATEGORY_ID,
            "product_type": PRODUCT_TYPE,
        })

    return products

def generate_xml():
    products = fetch_products()

    rss = ET.Element("rss", {
        "version": "2.0",
        "xmlns:g": "http://base.google.com/ns/1.0"
    })
    channel = ET.SubElement(rss, "channel")

    ET.SubElement(channel, "title").text = "3 Пирога — Каталог товарів (UA)"
    ET.SubElement(channel, "link").text = BASE_URL
    ET.SubElement(channel, "description").text = "Фід товарів з сайту 3piroga.ua (UA)"

    for product in products:
        item = ET.SubElement(channel, "item")

        ET.SubElement(item, "g:id").text = product["id"]
        ET.SubElement(item, "g:title").text = product["title"]
        ET.SubElement(item, "g:description").text = product["description"]
        ET.SubElement(item, "g:link").text = product["link"]
        ET.SubElement(item, "g:image_link").text = product["image_link"]

        ET.SubElement(item, "g:availability").text = product["availability"]
        ET.SubElement(item, "g:condition").text = product["condition"]
        ET.SubElement(item, "g:brand").text = product["brand"]
        ET.SubElement(item, "g:price").text = product["price"]

        # ВАЖЛИВО: категорії для Merchant/Ads (щоб не розкидало по "Одяг/Медіа/Електроніка")
        ET.SubElement(item, "g:google_product_category").text = product["google_product_category"]
        ET.SubElement(item, "g:product_type").text = product["product_type"]

        # Немає GTIN/MPN для власної випічки — кажемо Merchant явно
        ET.SubElement(item, "g:identifier_exists").text = "false"

        # НЕ використовуй g:size (це apparel-атрибут і може кидати в "одежу")
        # Вагу краще віддати як shipping_weight
        weight = product["weight"].replace("г", "").strip()
        ET.SubElement(item, "g:shipping_weight").text = f"{weight} g"

    rough_string = ET.tostring(rss, encoding="utf-8")
    reparsed = parseString(rough_string)
    formatted_xml = reparsed.toprettyxml(indent="  ")

    output_file = OUTPUT_DIR / FEED_FILE
    output_file.write_text(formatted_xml, encoding="utf-8")

    print(f"XML-файл {output_file} оновлено: {datetime.now()}")
    print(f"Товарів у фіді: {len(products)}")

if __name__ == "__main__":
    generate_xml()

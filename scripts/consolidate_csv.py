#!/usr/bin/env python3
import csv
from collections import defaultdict

# Read the original CSV
input_file = 'web/sites/default/files/fresh_submarines.csv'
output_file = 'web/sites/default/files/fresh_submarines_consolidated.csv'

products = defaultdict(lambda: {'skus': [], 'stores': None})

with open(input_file, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    for row in reader:
        product_id = row['product_id']
        sku = row['sku']
        stores = row['stores']

        if sku:  # Skip empty rows
            products[product_id]['skus'].append(sku)
            products[product_id]['stores'] = stores

# Write consolidated CSV
with open(output_file, 'w', encoding='utf-8', newline='') as f:
    writer = csv.writer(f)
    writer.writerow(['product_id', 'sku', 'stores'])

    for product_id, data in products.items():
        if data['skus']:
            sku_list = '|'.join(data['skus'])
            writer.writerow([product_id, sku_list, data['stores']])

print(f"Consolidated CSV created: {output_file}")
print(f"Original products: {len(products)}")

import pymysql
from pymysql.cursors import DictCursor

config = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'health_link',
    'cursorclass': DictCursor
}

try:
    conn = pymysql.connect(**config)
    print("Connection successful!")
    conn.close()
except Exception as e:
    print(f"Connection failed: {e}")
import psycopg2

DATABASE_URL = "postgresql://postgres:lgQtvjYAzizDocgIJBIwLUmJZToGkeNG@turntable.proxy.rlwy.net:44122/railway"

conn = psycopg2.connect(DATABASE_URL)
cur = conn.cursor()
cur.execute("SELECT id, email, password_hash FROM users")
rows = cur.fetchall()
for row in rows:
    print(f"id={row[0]} email={row[1]} hash_prefix={row[2][:20] if row[2] else 'NULL'}")
conn.close()

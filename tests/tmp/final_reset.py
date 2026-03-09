import psycopg2

DATABASE_URL = "postgresql://postgres:lgQtvjYAzizDocgIJBIwLUmJZToGkeNG@turntable.proxy.rlwy.net:44122/railway"
HASH = "$2b$12$Z1JUgKF0lc0YnRy0vFgSUed7qdXT0dpr1sxPHyzkRU55UomI0b01a"

conn = psycopg2.connect(DATABASE_URL)
cur = conn.cursor()
cur.execute("UPDATE users SET password_hash = %s WHERE email = 'mohan@pixels.net.nz'", (HASH,))
conn.commit()
cur.execute("SELECT password_hash FROM users WHERE email = 'mohan@pixels.net.nz'")
stored = cur.fetchone()[0]
print("Stored prefix:", stored[:20])
print("Match:", stored == HASH)
conn.close()
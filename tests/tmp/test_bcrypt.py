import bcrypt

PASSWORD = "Dtjx7WY4oSAMo329bweZpVcJg7oWt4q7ett"

hashed = bcrypt.hashpw(PASSWORD.encode('utf-8'), bcrypt.gensalt(12)).decode()
match = bcrypt.checkpw(PASSWORD.encode('utf-8'), hashed.encode('utf-8'))
print("Self-match:", match)
print("Hash:", hashed)
SCRIPT
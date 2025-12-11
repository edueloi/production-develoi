import sqlite3

# Caminho do banco de dados
DB_PATH = 'loja.db'

conn = sqlite3.connect(DB_PATH)
c = conn.cursor()

# Verifica colunas da tabela servicos
c.execute("PRAGMA table_info(servicos)")
columns = [col[1] for col in c.fetchall()]

if 'mostrar_site' not in columns:
    c.execute("ALTER TABLE servicos ADD COLUMN mostrar_site INTEGER DEFAULT 1")
    print('Coluna mostrar_site adicionada.')
else:
    print('Coluna mostrar_site já existe.')

conn.commit()
conn.close()

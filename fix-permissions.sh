#!/bin/bash

# Script per correggere permessi file nel progetto
# Esegui dopo aver creato nuovi file nei container Docker

echo "🔧 Correzione permessi file..."

# Imposta ownership su tutti i file del progetto
echo "📂 Impostando ownership mnossa:mnossa..."
sudo chown -R mnossa:mnossa /home/mnossa/www/finanzamente

# Imposta permessi corretti per storage e cache
echo "📁 Configurando permessi storage e cache..."
chmod -R 775 /home/mnossa/www/finanzamente/storage
chmod -R 775 /home/mnossa/www/finanzamente/bootstrap/cache

# Permessi specifici per file sensibili
echo "🔐 Proteggendo file sensibili..."
chmod 600 /home/mnossa/www/finanzamente/.env 2>/dev/null || true
chmod 600 /home/mnossa/www/finanzamente/.env.docker 2>/dev/null || true

# Rimuovi file hot se presente (verrà ricreato da Vite)
if [ -f /home/mnossa/www/finanzamente/public/hot ]; then
    echo "🔥 Rimuovendo file hot..."
    rm /home/mnossa/www/finanzamente/public/hot
fi

echo "✅ Permessi corretti!"

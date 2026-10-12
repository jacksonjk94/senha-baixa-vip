FROM node:22-alpine

WORKDIR /app

# Sem dependências externas: só server.js + package.json
COPY package.json server.js ./

ENV NODE_ENV=production \
    TZ=America/Sao_Paulo

CMD ["node", "server.js"]

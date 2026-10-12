FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev 2>/dev/null || npm install --omit=dev

COPY server.js ./

ENV NODE_ENV=production \
    TZ=America/Sao_Paulo

EXPOSE 3037

CMD ["node", "server.js"]

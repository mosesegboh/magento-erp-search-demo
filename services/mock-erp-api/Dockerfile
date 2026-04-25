FROM node:22-alpine

WORKDIR /usr/src/app

COPY package.json ./
COPY data ./data
COPY src ./src

USER node

EXPOSE 3001

CMD ["npm", "start"]

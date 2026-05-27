FROM node:22-alpine
WORKDIR /app
ENV HOST=0.0.0.0
ENV PORT=3000
# Install uses NPM_CONFIG_PRODUCTION=false in compose so @nuxtjs/tailwindcss is available for `nuxt build`.
ENV NODE_ENV=production
ENV NPM_CONFIG_PRODUCTION=false
ENV ZEP_PIXEL_AGENTS_ROOT=/workspace/zep-pixel-agents
ENV BOSSKU_AUTO_PIXEL_OFFICE=true
EXPOSE 3000

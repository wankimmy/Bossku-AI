import { joinURL } from 'ufo'

/**
 * Proxy /api/* to Laravel (nginx in Docker). Uses proxyRequest for POST SSE streams;
 * routeRules.proxy can mis-handle long-lived text/event-stream responses (502 Bad Gateway).
 */
export default defineEventHandler((event) => {
  const config = useRuntimeConfig()
  const upstream = String(config.apiProxyUpstream || 'http://127.0.0.1:28480/api').replace(/\/$/, '')
  const suffix = event.path.replace(/^\/api/, '') || ''
  const target = joinURL(upstream, suffix)

  return proxyRequest(event, target)
})

# Playbook: Docker / VPS deployment

**Skill summary:** [`../skills/docker.md`](../skills/docker.md)

**Deep playbook:** [`../ai-assistant/references/playbooks/bosskuai-vps-docker-deployment-playbook.md`](../ai-assistant/references/playbooks/bosskuai-vps-docker-deployment-playbook.md)

**Cross-links**

- Repo Docker quickstart: [`../README.md`](../README.md) (compose + importer)
- Observability add-on: [`../ai-assistant/skills/bosskuai-observability-sre/SKILL.md`](../ai-assistant/skills/bosskuai-observability-sre/SKILL.md)

**Workflow**

1. Orchestrator: inventory services, secrets handling, rollback.  
2. Executor: compose/Dockerfile/nginx changes only as needed.  
3. Auditor: ports, secrets, TLS, persistence.  
4. [`token-saving.md`](token-saving.md)

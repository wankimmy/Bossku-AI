export type OnboardingPlacement = 'top' | 'bottom' | 'left' | 'right' | 'center'

export type OnboardingStep = {
  id: string
  title: string
  body: string
  /** CSS selector; null = centered card (no spotlight target). */
  selector: string | null
  route?: string
  placement: OnboardingPlacement
  /** Step ids that may show a setup checkmark when hint is satisfied. */
  hintKey?: 'ollama' | 'project'
}

export const ONBOARDING_STEPS: OnboardingStep[] = [
  {
    id: 'welcome',
    title: 'Welcome to BosskuAI',
    body: 'This UI runs multi-agent workflows: route your task, plan, execute on your repo, audit, and review. This short tour highlights the essentials.',
    selector: null,
    placement: 'center',
  },
  {
    id: 'settings',
    title: 'Connect models',
    body: 'Set your Ollama base URL and API key (Ollama Cloud or host.docker.internal:11434 for local Ollama). Without this, runs cannot reach an LLM.',
    selector: '[data-tour="nav-settings"]',
    route: '/settings/models',
    placement: 'right',
    hintKey: 'ollama',
  },
  {
    id: 'project',
    title: 'Activate your repository',
    body: 'Register a folder under the Docker workspace mount (e.g. C:\\...\\your-repo mapped to /workspace/your-repo), then click Activate so agents can read and edit files.',
    selector: '[data-tour="nav-project"]',
    route: '/project',
    placement: 'right',
    hintKey: 'project',
  },
  {
    id: 'personas',
    title: 'Agent personas (optional)',
    body: 'Customize how each pipeline agent speaks. Personas prepend to system prompts on every chat message and handoff (orchestrator → executor → auditor, etc.).',
    selector: '[data-tour="nav-personas"]',
    route: '/personas',
    placement: 'right',
  },
  {
    id: 'chat',
    title: 'Main chat',
    body: 'Send tasks from here. BosskuAI classifies the prompt, may ask clarifying questions, then runs the orchestrator pipeline.',
    selector: '[data-tour="nav-chat"]',
    route: '/',
    placement: 'right',
  },
  {
    id: 'chat-prompt',
    title: 'Your first prompt',
    body: 'Example: "Audit the payment controller for security issues" or "Add a health check endpoint". Watch the agent activity panel on the right while the run progresses.',
    selector: '[data-tour="chat-prompt"]',
    route: '/',
    placement: 'top',
  },
  {
    id: 'runs',
    title: 'Run history',
    body: 'Every pipeline leaves a trace here: steps, models used, file changes, and audit results. Open a run to inspect timelines and artifacts.',
    selector: '[data-tour="nav-runs"]',
    route: '/runs',
    placement: 'right',
  },
  {
    id: 'done',
    title: 'You are ready',
    body: 'Explore Skills, Memory, Soul, and Data from the sidebar. See docs/quickstart.md in the repo for Docker setup and troubleshooting.',
    selector: null,
    placement: 'center',
  },
]

export function tourSelectorForDataTour(id: string): string {
  return `[data-tour="${id}"]`
}

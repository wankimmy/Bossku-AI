# Knowledge Graph

The knowledge graph is a live, interactive visualization of everything BosskuAI knows about your project and how it is connected. It surfaces relationships between skills, runs, memories, agents, and files that are not obvious from any single view.

## What the Graph Represents

The graph is a directed property graph where **nodes** are project entities and **edges** are the relationships between them.

### Node Types

| Type | What it represents |
|---|---|
| `skill` | An active skill in the skill library |
| `run` | A completed (or failed) AI run |
| `memory` | A persistent memory entry |
| `agent` | A named agent role (Orchestrator, Planner, Executor, Auditor, FinalReviewer) |
| `file` | A source file that was read or modified by a run |

Each node carries the properties most relevant to its type:
- **Skill nodes**: name, quality_score, version, status
- **Run nodes**: status, duration, total_cost, skill_used
- **Memory nodes**: content (truncated), confidence_score, tags, staleness
- **Agent nodes**: role, model_used
- **File nodes**: path, language, last_modified_by_run_id

### Edge Types

| Edge | Meaning |
|---|---|
| `run → skill` | This run used this skill |
| `run → memory` | This run cited this memory (memory was injected) |
| `run → memory` (reverse) | This run produced this memory |
| `run → file` | This run modified this file |
| `run → agent` | This agent participated in this run |
| `skill → skill` | Co-occurrence: these skills are often used together |
| `memory → memory` | Conflict: these memories contradict each other |

## How KnowledgeGraphBuilder Populates the Graph

`KnowledgeGraphBuilder` is invoked after every run completes. It:

1. Creates or updates a `run` node for the completed run
2. Creates a `run → skill` edge to the skill that was selected
3. Creates `run → memory` edges for each memory that was injected into the run context
4. Creates `memory → run` edges (reverse) for each memory extracted from the run output
5. Creates `run → file` edges for each file the executor touched
6. Creates `run → agent` edges for each service that participated
7. Updates `skill → skill` co-occurrence edges: if two skills were both candidates in the same run (even if only one was selected), their co-occurrence count increments
8. Updates `memory → memory` conflict edges when `MemoryConflictDetector` finds contradictions

The graph is stored in the `graph_nodes` and `graph_edges` tables and rendered client-side using Cytoscape.js.

## Using the Graph View

### Finding Patterns

The graph view is most useful when filtered to a specific skill node. Click any skill node to see:
- All runs that used this skill (radial layout)
- Which memories those runs drew on
- Which files were commonly touched
- Co-occurrence edges to related skills

Tightly clustered run nodes around a skill indicate high usage. Sparse clusters may mean the skill is rarely matched or was recently added.

### Spotting Conflicts

Memory nodes involved in conflicts are rendered with a red border. Clicking a conflicted memory node highlights both the conflicting memory and the runs that contributed each side of the conflict, making it straightforward to understand what changed and when.

### Identifying Weak Skills

Skill nodes are colored by quality score:
- Green: `quality_score >= 0.7`
- Yellow: `0.4 <= quality_score < 0.7`
- Red: `quality_score < 0.4`

A cluster of red-bordered run nodes attached to a skill is a strong signal that the skill's guidance is contributing to poor outcomes.

### Tracing a File's History

Click a file node to see all runs that modified it, in chronological order. This is useful for understanding why a file looks the way it does, and which skill/agent decisions drove each change.

## Graph Controls

The Cytoscape graph on the `/knowledge-graph` page supports:

- **Filter by node type** — show/hide specific node types with toggle buttons
- **Filter by date range** — limit run nodes to a time window
- **Search** — highlight nodes matching a text query
- **Layout options** — force-directed (default), hierarchical, radial
- **Zoom and pan** — standard mouse/trackpad controls
- **Export** — download the current view as PNG or the full graph as JSON

## Performance Notes

The full graph can be large on long-running projects. By default, the UI loads only the last 90 days of run nodes. Change the `graph_days` query parameter to expand the window (`?graph_days=365`). Graphs with more than 2000 nodes switch to a simplified edge rendering mode automatically.

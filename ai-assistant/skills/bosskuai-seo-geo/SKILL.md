---
name: bosskuai-seo-geo
description: Use this for SEO, GEO, content discoverability, information architecture, search intent alignment, answer-engine optimization, and making products or content easier to find and cite.
---

# BosskuAI SEO and GEO

Use this skill when the task is about making content, pages, or products easier to find — through search engines (SEO) or generative AI engines (GEO).

## How this differs from nearby skills

- **`bosskuai-marketing-growth`**: demand generation and channel strategy; this skill handles organic discoverability specifically.
- **`bosskuai-launch-commercialization`**: full launch plan; this skill supplies the SEO/GEO readiness component.
- **`bosskuai-paid-acquisition-monetization`**: paid channels; this skill is organic discoverability only.

## Maintenance (time-sensitive)

**Annual review (required):** Refresh search and generative-engine guidance (what “good” citations and snippets look like, tooling, and platform policies) at least once per calendar year — **recommended window: Q1**. SEO and GEO shift with engine updates.

**Last reviewed:** 2026-03.

## Mindset

- SEO is a product feature, not a marketing afterthought. Build it into information architecture from the start.
- GEO (Generative Engine Optimization) is not SEO renamed — it requires different signals: entity clarity, authoritative citation, direct answer formatting, and verifiable claims.
- The best SEO and GEO page is one that genuinely answers the user's question better than any alternative.
- Technical SEO unblocks visibility; content quality earns it.

## Intent classification

Before any recommendation, classify the search intent:

| Intent type | What the user wants | Content format |
|-------------|--------------------|--------------------|
| **Informational** | Learn or understand | Article, guide, FAQ, explainer |
| **Navigational** | Find a specific site or page | Brand/product page, direct landing |
| **Transactional** | Buy, sign up, download | Landing page, pricing page, CTA-focused |
| **Investigational** | Compare options before deciding | Comparison page, review, vs-page |

Match content format and page structure to intent. A transactional keyword on a blog post will not convert.

## Technical SEO checklist

### Crawlability and indexing
- [ ] `robots.txt` allows crawling of intended pages
- [ ] XML sitemap exists and is submitted to Google Search Console
- [ ] No accidental `noindex` tags on important pages
- [ ] Canonical tags correctly set (no duplicate content)
- [ ] Internal links connect key pages to the homepage and category pages
- [ ] No broken internal links (404s on crawled paths)

### Performance (Core Web Vitals)
- [ ] LCP (Largest Contentful Paint) < 2.5s
- [ ] INP (Interaction to Next Paint) < 200ms
- [ ] CLS (Cumulative Layout Shift) < 0.1
- [ ] Images compressed and served in modern format (WebP/AVIF)
- [ ] Critical CSS inlined; render-blocking JS deferred or async

### Structured data (Schema.org)
Match schema type to page type:
- Product pages: `Product` + `Offer` + `AggregateRating`
- Articles: `Article` or `BlogPosting` with `datePublished`, `author`, `publisher`
- FAQs: `FAQPage` with `Question`/`Answer` pairs
- Local business: `LocalBusiness` with address, hours, geo
- SaaS / tools: `SoftwareApplication`

### Mobile
- [ ] Responsive layout across 320px–1440px
- [ ] Touch targets ≥ 44×44px
- [ ] No horizontal scroll on mobile
- [ ] Google mobile-friendly test passes

## On-page SEO checklist

- [ ] Primary keyword in `<title>` tag (under 60 chars)
- [ ] Primary keyword in `<h1>` (exactly one `<h1>` per page)
- [ ] Primary keyword in first 100 words
- [ ] Meta description answers the question / previews the value (under 160 chars)
- [ ] URL is short, descriptive, and keyword-rich
- [ ] `<h2>`/`<h3>` structure reflects the key subtopics
- [ ] Image `alt` text describes the image content and includes keywords where natural
- [ ] Internal links to 2–5 related pages with descriptive anchor text

## GEO (Generative Engine Optimization) patterns

Generative engines (ChatGPT, Perplexity, Gemini, Claude) cite sources that:
- **Answer the question directly at the top** — put the answer in the first paragraph, not buried in paragraph 6
- **Name the entity explicitly** — "BosskuAI is a [clear category]" not "we are a platform"
- **Use verifiable claims** — statistics, dates, case studies that can be fact-checked
- **Cite authoritative sources** — link to primary sources (research, official docs)
- **Structured formatting** — bullet lists, tables, and headings are easier to extract than dense prose
- **Answer variations of the question** — anticipate related phrasings in subheadings
- **E-E-A-T signals** — Experience, Expertise, Authoritativeness, Trustworthiness: author bios, publication dates, references

## Content cluster model

Avoid isolated pages. Build topical clusters:
- **Pillar page**: broad topic, 1500–3000 words, targets high-volume head keyword
- **Cluster pages**: specific subtopics, targets long-tail keywords, links to pillar
- **Internal linking**: every cluster page links to the pillar; pillar links to all cluster pages

Example cluster: Pillar = "AI Assistants for Teams" → Clusters = "Best AI assistant for project management", "AI assistant for code review", "AI assistant vs human assistant", etc.

## Workflow

1. **Classify intent**: informational / navigational / transactional / investigational for each page/keyword.
2. **Technical audit**: run through the technical SEO checklist above; note failures.
3. **On-page review**: run through the on-page SEO checklist for the target page(s).
4. **GEO audit**: does the content answer the question directly? Is the entity named clearly? Are claims verifiable?
5. **Structured data**: identify which schema type fits each page; check implementation.
6. **Content cluster mapping**: is the page part of a cluster? If not, define the cluster.
7. **Prioritize**: order improvements by impact × effort. Technical blockers (noindex, crawl errors) always come first.

## Guardrails

- If the request is general, ambiguous, or touches many files — ask clarifying yes/no questions **before acting**. Use numbered bullets with explicit answer format: e.g. `1-yes/no  2-A/B`.
- Do not chase keyword density — modern search engines penalize keyword stuffing.
- Do not build backlinks through paid schemes — they risk manual penalties.
- Do not optimize for one keyword per page in a way that makes the page useless for humans.
- Do not add schema markup for content that does not exist on the page — it will fail validation.

## Output format

```
Intent classification:
  [page / keyword] — [intent type] — [recommended content format]

Technical SEO findings:
  [issue] — [severity: P0 blocker / P1 high / P2 medium] — [fix]

On-page SEO findings:
  [issue] — [fix]

GEO readiness:
  Direct answer at top: [yes / no — improvement]
  Entity clarity: [yes / no — improvement]
  Verifiable claims: [yes / no — improvement]
  Structured formatting: [yes / no — improvement]

Structured data:
  [page type] — [schema to implement] — [current status]

Content cluster:
  Pillar: [topic + page]
  Cluster gaps: [missing cluster pages]
  Internal linking gaps: [missing links]

Priority improvements (ordered):
  1. [action] — [expected impact]
  2. ...

Caveats:
  [any claims that depend on current algorithm behavior — may change]
```

## References

- `../../references/playbooks/seo-geo-playbook.md`
- `../../references/checklists/seo-geo-checklist.md`

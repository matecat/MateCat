# Context Preview — Future Developments

This document outlines planned improvements and open design questions for the Context Preview feature.

---

## JavaScript Execution in Preview Panels

### Current Behavior

All `<script>` tags are stripped from fetched HTML documents before rendering in the preview panels. Scripts inserted via `innerHTML` are inert by HTML spec, so even prior to stripping they never executed — the removal simply reduces DOM bloat and makes the no-execution policy explicit.

### Discussion: Allowing Trusted Scripts

The question was raised whether scripts hosted on `files.sandbox.translated.com` (Translated's own infrastructure) could be treated as safe and retained in the preview HTML, since they power JS-driven layouts (accordions, tab panels, lazy-loaded content) that would otherwise remain in their initial/hidden state.

### Decision

External scripts must **not** be retained in the rendered HTML — even when originating from Translated-owned domains — for the following reasons:

1. **Unverified execution context.** Customer-uploaded files may contain arbitrary JavaScript. The fact that a script is hosted on a Translated domain does not guarantee its contents have been audited or sandboxed. A customer could upload an HTML file referencing scripts that make external network calls, exfiltrate data, or interact with browser APIs in unexpected ways.

2. **CSP bypass risk.** If the Content-Security-Policy of the preview page allows `files.sandbox.translated.com` as a script source, any script hosted there — including those uploaded by customers — would execute with full CSP approval. This effectively turns a trusted origin into an attacker-controlled execution surface.

3. **Domain trust is not content trust.** Allowlisting a domain in CSP means trusting *every resource* served from that domain. Since the sandbox hosts user-uploaded content, domain-level trust does not imply content-level trust.

### Possible Future Approach: Isolated CSP Header

The only scenario in which script execution could be safely re-enabled is by generating a **dedicated HTML response** with a restrictive CSP that completely isolates the preview's execution environment:

```
Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://files.sandbox.translated.com; img-src https://files.sandbox.translated.com data:; font-src https://files.sandbox.translated.com; script-src 'none'
```

Key properties of this approach:

- **`script-src 'none'`** — No scripts execute at all, regardless of origin. This is the maximum-isolation baseline.
- **Selective relaxation** — If specific trusted scripts are identified and audited, `script-src` could be narrowed to a hash-based or nonce-based allowlist rather than a domain allowlist.
- **Separate document context** — The preview would need to be served as a full HTML document (e.g., via an iframe with `sandbox` attributes) rather than injected into the main page's DOM, so the CSP applies independently.
- **`sandbox` iframe attributes** — Combine CSP with `<iframe sandbox="allow-same-origin">` (without `allow-scripts`) for defense-in-depth. The `sandbox` attribute provides a browser-enforced execution boundary even if CSP is misconfigured.

### Risks to Evaluate Before Implementation

| Risk | Description | Mitigation |
|------|-------------|------------|
| XSS via inline event handlers | `onclick`, `onerror`, etc. attributes in customer HTML can execute JS even without `<script>` tags | Strip inline event handler attributes during parsing, or rely on CSP `script-src 'none'` to block them |
| Data exfiltration | Scripts could send document content to external endpoints | `connect-src 'none'` in CSP; `sandbox` without `allow-scripts` |
| DOM manipulation | Executed JS could interfere with segment tagging and highlighting | Isolation via iframe/shadow DOM boundary |
| Resource loading side-effects | CSS/fonts/images could leak referrer or timing information | `referrerpolicy="no-referrer"` on the iframe |
| Performance | Executing customer JS could block the main thread or cause infinite loops | Isolated iframe prevents main-thread blocking; consider `allow-scripts` only with a timeout/kill mechanism |

### Recommendation

Maintain the current no-script policy. If JS-driven layouts cause significant usability issues (many hidden nodes), explore the isolated-iframe approach with `sandbox` + strict CSP as a follow-up initiative rather than relaxing script execution in the existing Shadow DOM panels.

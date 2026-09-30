# AGENTS.md — Slagger Development Guardrails

This document defines architectural boundaries, quality constraints, and operational guidelines for autonomous AI agents working in this repository.

---

## 1. Project Mission & Identity

- Package Name: eduardomachado93/slagger (Namespace: Slagger\)
- Objective: Automatically produce OpenAPI 3.0 specs and serve Swagger UI in Slim 4 micro-framework projects via native PHP 8 Reflection and Attributes.
- Runtime Target: PHP 8.2+ with Slim 4.12+.

---

## 2. Core Architectural Invariants

1. Zero Annotation Bloat: Never pull doctrine/annotations or heavy external document parsers. Rely exclusively on native PHP 8 attributes (ReflectionAttribute) and standard PHP reflection APIs (ReflectionClass, ReflectionMethod, ReflectionNamedType).
2. Framework Decoupling: Core generation routines must operate strictly against PSR-7/PSR-15 interfaces and standard Slim 4 RouteCollector. Do not couple the codebase to concrete HTTP server implementations.
3. Pure Execution: Do not introduce side-effects. Core classes must return raw specification arrays or PSR-7 responses. Avoid echo, print, die, or exit inside src/.
4. Resilient Route Fallbacks: If an endpoint uses an anonymous Closure or omits explicit attributes, Slagger must still generate a valid OpenAPI operation using sensible fallbacks (e.g., standard 200 response and method/URI-derived summary).

---

## 3. Strict Coding Standards

- Strict Types: Every PHP file must declare strict types as its first statement: declare(strict_types=1);
- Type Completeness: Every property, parameter, and return value must have explicit types. Replace untyped arrays with explicit PHPDoc array shapes (e.g., array<string, mixed>).
- Static Analysis Target: Code must pass PHPStan Level 8 with zero errors.
- Style Consistency: Follow PSR-12 coding standard. Class names in PascalCase, methods and variables in camelCase.

---

## 4. Layer Responsibility Matrix

| Path | Primary Role | Permitted Dependencies | Forbidden |
| :--- | :--- | :--- | :--- |
| src/Attributes/ | Metadata contracts | Native PHP attributes only | Any framework or third-party packages |
| src/Parser/ | Path transformation | PHP standard string/regex functions | Slim classes, PSR interfaces |
| src/Extractor/ | Route table extraction | Slim\App, Slim\Interfaces\* | DTO Reflection, HTTP request cycle |
| src/Inspector/ | Callable resolution | Standard Reflection API | Slim Application state, Networking |
| src/Schema/ | DTO schema mapper | Standard Reflection API | Route collectors, Slim components |
| src/Ui/ | Swagger UI Presenter | PSR-7 Message interfaces | Core generation business logic |
| src/Slagger.php | Root Orchestrator | Internal Slagger\* modules | Direct hardcoded schema definitions |

---

## 5. Verification Checklist

Before finishing any task, run and pass both commands:

- Static analysis: ./vendor/bin/phpstan analyse -c phpstan.neon
- Test suite: ./vendor/bin/phpunit

Explicit Prohibitions:
- Do NOT lower PHPStan analysis level below 8.
- Do NOT introduce breaking changes to existing fixtures in tests/Fixtures/.
- Do NOT install runtime dependencies beyond slim/slim and cebe/php-openapi without explicit instruction.
- Do NOT leave incomplete stubs, empty methods, or // TODO placeholders.
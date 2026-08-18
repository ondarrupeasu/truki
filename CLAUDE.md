# Truki

**Qué es:** proyecto **padre-hija** (Alex + su hija y un par de compañeras de clase). Nació como trabajo de
**Economía**: una plataforma para **intercambiar ropa y accesorios SIN dinero** (trueque), tipo "Wallapop pero
cambiando en vez de vendiendo", con **match estilo Tinder** (deslizar prendas y hacer match) y un sistema de
**puntos ("trukipuntos")**. Nombre del euskara **trukatu / truke** = intercambiar. (Grafía: **Truki**.)

## Origen de la idea (de la hija)
- Subes ropa/accesorios que ya no quieres; en vez de pagar, **intercambias** por lo de otra persona.
- **Match tipo Tinder**: deslizas prendas, cuando hay interés mutuo → match.
- **Puntos:** cada prenda vale X puntos; si para un intercambio no te llegan, el sistema te ofrece **comprar más
  puntos (trukipuntos)** → esa es la vía de ingresos. (Modelo calcado de HomeExchange/GuestPoints y de BONNEE.)
- **Matiz HomeExchange (bueno):** si ambas partes están de acuerdo, el swap sale adelante aunque sea desigual en
  puntos; los puntos solo entran para "cuadrar" cuando hace falta. Flexible y humano.

## Auditoría de mercado (hecha en MissionControl, ago 2026)
- **Sí existe competencia, pero es un nicho EMERGENTE, no saturado** (la reventa Vinted/Wallapop sí lo está; el
  intercambio PURO no, y en España/Euskadi no hay líder claro).
- **Competidores directos:** BONNEE (Swap Coins por niveles), **Nuw** (token por prenda), **Swoopd** (swipe+swap
  tipo Tinder — la idea de la hija ya existe), **Closest Closet** ("hangers" como moneda), Tooused, The Swap Club.
  **España:** **Waki** (trueque general), Swappito, Ropantic (eventos). Vinted tiene opción de intercambio.
- **Mercado:** informe estima apps de swap ~1,8 B$ (2025) → 5,6 B$ (2034), ~13%/año (con pinzas, pero categoría al alza).
- **Validación:** la hija llegó sola al "manual del sector" (swipe + puntos + comprar puntos). Buen instinto.

## Ingresos (el punto flojo del swap, resuelto con puntos)
Comprar **trukipuntos** (principal) · suscripción premium (más swaps/destacar) · gastos de envío (los paga quien
recibe) · destacar prendas · verificación de marcas · alianzas con marcas/tiendas · publicidad con cuidado.

## Retos honestos (los que hunden a otros)
1. **Liquidez / huevo-gallina** (EL problema): sin ropa + gente de tu talla/zona no hay match.
2. **Valorar las prendas** (cuántos puntos) — sistema simple y justo o hay disputas (BONNEE=niveles; eventos=colores).
3. **Logística/envío** = fricción y coste. 4. **Confianza/estado de la prenda** = fraude/calidad.

## La jugada ganadora (y el enfoque del MVP)
- **HIPER-LOCAL:** arrancar en un **instituto / barrio / Bilbao-Bizkaia**, público joven → densidad = hay matches,
  y **quedadas en persona = sin envíos ni coste.** Perfecto para MVP y para lo educativo.
- **Identidad vasca** (Truki, de aquí) + **swipe divertido** + **puntos justos** + **sostenibilidad** (moda circular,
  anti fast-fashion → atrae público joven y posible apoyo del centro).

## Plan técnico (fases)
- **Fase 0 — Prototipo clicable (recomendado empezar aquí):** una **PWA/mockup interactivo** con datos falsos
  (deslizar prendas, ver match, gastar/ganar trukipuntos) → para **enseñar la idea y que la hija itere la UX** sin
  backend. Rápido y motivador. Como las webs estáticas de Alex (GitHub Pages).
- **Fase 1 — MVP real:** ya necesita **backend** (cuentas, catálogo de prendas, matching, saldo de puntos) → auth +
  BD (tipo Neon) + API. Es una app más completa que SoundLab; a diferencia de las webs docentes, aquí SÍ hay datos de
  usuarios. Empezar hiper-local para no morir en la liquidez.
- **Definir pronto:** las **reglas de puntos/valoración** (por categoría + estado, simple y justo) y el flujo de
  match→acuerdo→intercambio (con el matiz "si ambos aceptan, sin cuadrar puntos").

## Primer paso en la sesión
Con Alex (y la hija si puede): decidir empezar por el **prototipo clicable** (Fase 0) para validar UX y motivar, y
fijar las reglas de trukipuntos. NO es del ecosistema CinemaFilmak (es proyecto aparte; dominio propio a decidir).
Nombre/marca: **Truki**. Idioma UI: castellano + guiño euskara.

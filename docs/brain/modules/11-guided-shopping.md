# Modulo Guided Shopping (Wizard)

Proposito
- Asistente para recomendar packs segun occasion, budget y preference.
- Generar upsells contextuales y registrar eventos.

Componentes y archivos
- `Frontend/WizardShortcode.php` define `[bressol_guided_shopping]`.
- `Frontend/AddToCartRedirector.php` vuelve al wizard tras add-to-cart.
- `Wizard/WizardSession.php` guarda estado (WC session o PHP session).
- `Wizard/PackRecommender.php` recomienda packs por tier/price.
- `Wizard/UpsellRecommender.php` propone upsells (gift box/card + upgrade de tier).

Flujo principal
1) Usuario rellena form (occasion, budget, preference).
2) `WizardSession` guarda estado.
3) `PackRecommender` filtra packs por tier/price y occasion.
4) `UpsellRecommender`:
   - si gift -> anade productos extra (gift box/card).
   - si budget low/mid -> sugiere upgrade de tier.
5) Links add-to-cart incluyen `bressol_gs_return` para volver al wizard.

Tracking
- En el frontend se empuja `guided_upsell_add_to_cart` antes de navegar.
- `AddToCartRedirector` tambien guarda el evento en sesion si detecta add-to-cart.

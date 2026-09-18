# International product contract

Authority: PLAN-001 revision 3, user approval on 2026-09-18. VeLog targets international shops. Fixed Vietnam-only units/currency are rejected. This contract translates that requirement into design constraints; it is not evidence of implemented behavior or worldwide certification.

## Configuration and ownership

- One shop per installation remains approved. Region, distance unit and currency are explicit manager settings, not inferred permanently from IP, UI language or country. Country may suggest defaults, but the manager can override each independently.
- Required MVP distance units: kilometers (km) and international miles (mi). Imported vehicles can retain a different odometer unit from the shop display preference. Label every input and display with its unit.
- Require explicit distance/currency selection during setup before product writes requiring those settings. Do not silently assume km, VND, USD, or a currency from the site's language.
- Reuse the site's WordPress timezone and date/time presentation settings. The active WordPress user/site locale controls translated UI and number presentation; changing a language must not change stored units, currency or business dates.
- Keep validation/conversion/formatting in shared Common services; Admin presents settings, Core wires hooks. All later APIs must use the same services. No new dependency is authorized by this contract.

## Distance storage and comparisons

- Store each original odometer/threshold value as a validated nonnegative decimal and its explicit unit. Never reinterpret old numeric values when a shop or vehicle preference changes.
- Derive a canonical distance for comparisons using fixed-precision decimal/integer arithmetic; design target is integer millimeters, accepting at most three decimal places in km/mi. One international mile is exactly 1,609.344 meters. Specify overflow bounds and half-up rounding in the regional task; do not silently truncate, accept exponents or use binary floats for persisted conversions.
- Preserve original value/unit alongside any canonical value. Display rounding must not influence reminder due comparisons. Convert from the original/canonical source, never repeatedly from an already rounded display.
- Unknown readings remain unknown, not zero. Historical correction and replacement-odometer rules remain explicit vehicle/service task gates.
- Unit changes affect preferences for future entry/display; they do not bulk-rewrite history. Cross-unit reminder comparison must give the same result before and after preference changes.

## Currency and monetary values

- Support a manager-selected currency code with an explicit minor-unit scale; never assume two decimal places. Test zero-, two- and three-decimal currencies, such as JPY, USD/EUR and KWD.
- Use a maintained, versioned currency catalog with recorded provenance when the regional task is prepared. If no bundled catalog is available, Architect must specify validated configuration before Builder proceeds; do not invent exchange rates or add a package silently.
- Persist a fixed-precision amount plus currency code and scale on each cost record. Locale-specific grouping and decimal marks are presentation/input parsing, not storage format.
- Changing shop currency affects future defaults only. Historical amounts retain their original currency; never relabel or aggregate unlike currencies. Currency exchange, invoicing, taxation and payment processing remain outside MVP.

## Dates, language and identifiers

- Store date-only service/due values as calendar dates, separate from event timestamps. Store audit instants in UTC; compare due calendar dates in the configured shop timezone. Test DST transitions and midnight boundaries. No manual addition of numeric timezone offsets.
- Use WordPress translation APIs with the velog text domain, complete translatable phrases, plurals and context. Load translations at init or later; English source strings are the fallback. Translation-ready does not mean all language catalogs have been delivered.
- Support Unicode names, notes and registration plates. Do not impose Vietnamese phone lengths, address fields or plate regexes. A VIN/plate normalization and duplicate policy is still required in the vehicle task; do not strip meaningful non-Latin characters to force ASCII.
- UI layouts must accommodate long translations and RTL when UI is introduced; use logical layout properties and explicit direction where needed for identifiers/numbers. Test keyboard use and labels in each relevant UI task.
- Never assume a comma or dot can be removed safely from numeric input. Specify locale-aware parsing and reject ambiguous/malformed values with field feedback. Keep machine serialization locale-neutral.

## Other measurement dimensions

Do not implement unused product fields merely to populate a unit selector. When a feature introduces them, its schema must include dimension, unit, precision and conversion rules. Future coverage includes pressure (kPa/bar/psi), temperature (Celsius/Fahrenheit), volume (liters/US gallons/Imperial gallons), mass (kg/lb), dimensions (mm/cm/in) and consumption (L/100 km, km/L, US mpg, Imperial mpg). US and Imperial gallons/mpg must remain distinct. Mixed regional preferences are valid; a global metric/imperial toggle alone is insufficient. These are extension requirements, not added MVP features.

## Required acceptance matrix

| Scenario | Required result |
|---|---|
| en_US, miles, USD | Correct decimal/units, two-decimal cost, unchanged canonical distance |
| de_DE, km, EUR | Comma decimal input/display validated without corrupting stored numbers |
| en_GB, miles, GBP | Miles independent of country/currency and other future unit dimensions |
| ja, km, JPY | Zero-decimal currency and Unicode customer/vehicle data |
| ar, km, KWD | Three-decimal currency, RTL layout and translated/fallback strings |
| Change unit/currency/locale | Historical values and currency identity preserved; reminder due result unchanged |
| Timezone/DST, unknown/zero, invalid/overflow values | Explicit, tested boundary behavior; no fabricated readings or dates |

Locales are representative fixtures, not a promise that all translations or jurisdictions are supported. Full regional implementation belongs to a separate bounded task after CORE-001. Detailed schema, parsing examples, conversion limits, currency-catalog source and UI criteria must be specified before that task becomes READY.

## Sources checked during planning

- [WordPress internationalization guide](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) and [WordPress 6.7 i18n changes](https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/): translation lifecycle guidance.
- [NIST unit conversion factors](https://www.nist.gov/pml/us-surveyfoot/revised-unit-conversion-factors): exact international-mile conversion.
- Local WordPress source contains determine_locale(), wp_timezone(), wp_date() and number_format_i18n(); presence was inspected, proposed integration has not been tested.

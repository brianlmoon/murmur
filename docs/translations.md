# Translations

Murmur supports multiple languages through the Symfony Translation component. This guide explains how to configure the language and add new translations.

## Setting the Language

The site language is configured site-wide in **Admin → Settings → Language**. This setting applies to all users.

## Translation Files

Translation files are stored in the `translations/` directory at the project root:

```
translations/
└── messages.en-US.yaml    # English (US) - default
```

Each file uses the naming pattern `messages.{locale}.yaml`, where `{locale}` is an IETF language tag like `en-US`, `es-MX`, or `fr-FR`.

## Adding a New Language

1. **Copy the English file** as your starting point:
   ```bash
   cp translations/messages.en-US.yaml translations/messages.es-ES.yaml
   ```

2. **Translate all strings** in the new file. Keep the keys unchanged, only modify the values.

3. **Refresh the admin settings page** — the new language will automatically appear in the Language dropdown.

## Translation File Format

Translation files use YAML format with hierarchical keys:

```yaml
# Authentication
auth:
    login_title: "Iniciar sesión"
    login_button: "Entrar"
    email_label: "Correo electrónico"
    password_label: "Contraseña"

# Navigation
nav:
    feed: "Inicio"
    topics: "Temas"
    messages: "Mensajes"
```

### Key Naming Convention

Keys are organized hierarchically by feature area:

| Prefix | Description |
|--------|-------------|
| `common` | Shared UI elements (buttons, labels) |
| `nav` | Navigation links |
| `auth` | Login and registration |
| `feed` | Main feed page |
| `post` | Post display and actions |
| `compose` | Post/reply composition |
| `profile` | User profiles |
| `settings` | User settings |
| `messages` | Private messaging |
| `topics` | Topic browsing |
| `errors` | Error pages |
| `dates` | Date format strings |
| `relative` | Relative date strings |

## Pluralization

Use ICU message format for strings that vary by count:

```yaml
profile:
    followers: "{count, plural, =1 {1 follower} other {# followers}}"
    following: "{count, plural, =1 {1 following} other {# following}}"

post:
    comment_count: "{count, plural, =0 {} =1 {(1)} other {(#)}}"
```

In templates, pass the `count` parameter:

```twig
{{ 'profile.followers'|trans({'count': follower_count}) }}
```

### Plural Rules

The ICU format supports these selectors:

- `=0` — Exactly zero
- `=1` — Exactly one
- `=2` — Exactly two (useful for some languages)
- `other` — All other numbers

The `#` symbol is replaced with the actual count.

## Variable Substitution

For strings with dynamic content, use the `%variable%` syntax:

```yaml
profile:
    joined: "Joined %date%"
    no_posts: "%username% hasn't posted anything yet."
```

In templates:

```twig
{{ 'profile.joined'|trans({'%date%': user.created_at|localized_date('month_year')}) }}
```

## Date Formats

Date format strings use PHP's `date()` format characters. Customize these per locale:

```yaml
dates:
    format_short: "M j"           # Dec 27
    format_long: "F j, Y"         # December 27, 2025
    format_time: "g:i A"          # 1:39 AM
    format_datetime: "F j, Y g:i A"  # December 27, 2025 1:39 AM
    format_month_year: "F Y"      # December 2025
```

For European format:

```yaml
dates:
    format_short: "j M"           # 27 Dec
    format_long: "j F Y"          # 27 December 2025
    format_time: "H:i"            # 13:39
    format_datetime: "j F Y H:i"  # 27 December 2025 13:39
    format_month_year: "F Y"      # December 2025
```

### Using Localized Dates in Templates

Use the `|localized_date` filter instead of `|date`:

```twig
{# Before #}
{{ post.created_at|date('F j, Y') }}

{# After - uses format from translation file #}
{{ post.created_at|localized_date('long') }}
```

Available format names:
- `short` — Abbreviated date
- `long` — Full date
- `time` — Time only
- `datetime` — Full date and time
- `month_year` — Month and year

## Locale Codes

Use IETF language tags with the format `{language}-{REGION}`:

| Code | Language |
|------|----------|
| `en-US` | English (United States) |
| `en-GB` | English (United Kingdom) |
| `es-ES` | Spanish (Spain) |
| `es-MX` | Spanish (Mexico) |
| `fr-FR` | French (France) |
| `fr-CA` | French (Canada) |
| `de-DE` | German (Germany) |
| `pt-BR` | Portuguese (Brazil) |
| `ja-JP` | Japanese (Japan) |
| `zh-CN` | Chinese (Simplified) |
| `zh-TW` | Chinese (Traditional) |

## Using Translations in Templates

### Simple Strings

```twig
<button type="submit">{{ 'auth.login_button'|trans }}</button>
```

### With Variables

```twig
<p>{{ 'profile.joined'|trans({'%date%': user.created_at|localized_date('month_year')}) }}</p>
```

### Pluralization

```twig
<span>{{ 'profile.followers'|trans({'count': follower_count}) }}</span>
```

## Fallback Behavior

When a translation is missing for the configured locale, Murmur automatically falls back to English (`en-US`). This ensures users always see working text even if a translation is incomplete.

## Best Practices

1. **Keep the English file complete** — It serves as the fallback and reference for all other translations.

2. **Use descriptive keys** — Keys like `auth.login_button` are easier to understand than `button1`.

3. **Group related strings** — Keep all authentication strings under `auth:`, all feed strings under `feed:`, etc.

4. **Test pluralization** — Verify strings display correctly for 0, 1, and multiple counts.

5. **Consider context** — A word may translate differently depending on usage. Use more specific keys if needed.

6. **Review date formats** — Date conventions vary by region. Ensure formats are appropriate for each locale.

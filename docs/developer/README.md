# WE Formkit — developer guide

Formkit is built so field types and product features can be extended without forking core.

## Register a field type

```php
add_action(
	'we_formkit_register_field_types',
	static function ( \Webentwicklerin\WeFormkit\Field_Type_Registry $registry ) {
		$registry->register( new My_Custom_Field() );
	}
);
```

Your class must extend `Webentwicklerin\WeFormkit\Fields\Abstract_Field_Type` and implement at least `get_type()` and `get_label()`. Override `normalize_config()`, `sanitize()`, `validate()`, and `render_attributes()` as needed.

### Matrix field

Type id: `matrix`. Config in `type_options`:

- `row_select` (bool) — checkbox to mark each row as selected (`on`); unchecking clears that row’s answers
- `min_answered_rows` (int, default `0`) — minimum fully answered rows (`0` = optional). Replaces the generic field `required` toggle for matrix (legacy `required: true` migrates to `min_answered_rows: 1`)
- `rows` — `{ value, label, required? }[]` (`required` = that catalog row must be answered)
- `columns` — `{ id, type: radio|checkbox|text|number, label, required?, options? }[]` (`required` enforced only on **active** rows: selected via row checkbox, or filled / required preset when no row select)

Stored value: `{ row_id: { on?: bool, col_id?: string|bool|number, … }, … }`.

**Answered row:** active, custom label present if custom, and all required columns filled.

**Conditionals:** pick `Matrix label › Row label` in the Depends-on list (field key `matrix_id.row_id`) with operator **is checked**.

### Consent field

Type id: `consent`.

- **`label`** — field title (optional via `show_label`, like other fields)
- **`type_options.choice_label`** — text beside the checkbox (consent copy)
- Optional inline link via **`{link}`** inside `choice_label`:
  - `type_options.link_text` — anchor text (default: “Privacy policy”)
  - `type_options.privacy_url` — URL (empty → form privacy URL, then plugin/site default)

Example consent text: `I agree to the {link}.`  
Without `{link}`, no privacy link is rendered.

### Checkbox field

Type id: `checkbox`. Same split: `label` (title) + `type_options.choice_label` (text beside the control).

Legacy schemas that only had the control copy in `label` are migrated on normalize (`choice_label` ← old label, `show_label` → false).

## Register a module

```php
add_action(
	'we_formkit_register_modules',
	static function ( \Webentwicklerin\WeFormkit\Module_Registry $modules ) {
		$modules->add(
			'example',
			array(
				'name'         => 'Example Module',
				'description'  => 'Demonstrates the module API.',
				'version'      => '1.0.0',
				'supports'     => array( 'importer' ),
				'dependencies' => array(
					array(
						'label' => 'Some plugin active',
						'check' => static function () {
							return defined( 'SOME_PLUGIN_VERSION' );
						},
					),
				),
				'bootstrap'    => static function () {
					// Runs only when the module is activated in Formkit → Modules
					// and all dependencies are met.
				},
			)
		);
	}
);
```

Activate modules under **Formkit → Modules**. Core spam (honeypot, timing, rate limit, per-field link/email guards) always runs; modules add optional integrations.

## Useful hooks

### Targeting a form or field

Formkit uses **one global hook name** and passes `$form_id` / `$field` (and related context) as arguments. Scope inside the callback — there are no `we_formkit_*_{form_id}` / `_{field_id}` suffix hooks.

```php
// One form only.
add_filter(
	'we_formkit_pre_submission_data',
	static function ( $data, $schema, $form_id ) {
		if ( 12 !== (int) $form_id ) {
			return $data;
		}
		// …
		return $data;
	},
	10,
	3
);

// One field only (field id from the builder).
add_filter(
	'we_formkit_format_field_value',
	static function ( $display, $value, $field, $context ) {
		if ( ( $field['id'] ?? '' ) !== 'phone' ) {
			return $display;
		}
		// …
		return $display;
	},
	10,
	4
);
```

Match on `$field['type']` when you care about all fields of a type; combine with `$form_id` when the hook provides it (or look up the form from `$submission_id` / export `$post` when needed).

### Extension points

| Hook | Type | When |
|------|------|------|
| `we_formkit_register_field_types` | action | After core field types are registered `( $registry )` |
| `we_formkit_register_modules` | action | Collect module definitions `( $registry )` — call `$registry->add( $id, $definition )` |
| `we_formkit_module_registered` | action | After an **active + dependency-satisfied** module has been bootstrapped `( $id, $definition )` |
| `we_formkit_submission_created` | action | After a submission is stored `( $submission_id, $context )` — `$context` has `form_id`, `data`; notifications listen here |

### Filters — submit / mail / smart tags

| Hook | When |
|------|------|
| `we_formkit_spam_check` | After field validation, before save `( $result, $data, $schema, $form_id )` — return `WP_Error` to reject, otherwise leave `$result` / `null` |
| `we_formkit_pre_submission_data` | After spam check + signature persist, before entry save `( $data, $schema, $form_id )` — return mutated values array |
| `we_formkit_confirmation` | After smart-tag merge, before REST response `( $confirmation, $submission_id, $form_id, $data )` — keys `mode`, `message`, `redirect_url`, `page_url` |
| `we_formkit_submit_response` | Full successful submit REST body `( $response, $submission_id, $form_id, $data )` — may add keys for the frontend |
| `we_formkit_format_field_value` | Formatted field string `( $display, $value, $field, $context )` — `$context`: `email`, `admin`, `display`, `export` |
| `we_formkit_export_entry_row` | CSV list-export row `( $row, $post, $data, $header )` |
| `we_formkit_merge_tag_catalog` | Admin smart-tag picker catalog `( $items, $schema, $context )` — `$context` is `email` or `confirmation` |
| `we_formkit_merge_vars` | Tag replacement map before merge `( $vars, $submission_id, $form_id, $notification )` |
| `we_formkit_notification_mail` | Notification mail args before `wp_mail` `( $mail, $notification, $submission_id, $form_id )` |
| `we_formkit_resume_mail` | Save & Resume email before send `( $mail, $form_id, $resume_url, $expires )` — keys include optional `attachments` for .ics |

### Filters — capabilities / uploads

| Hook | When |
|------|------|
| `we_formkit_can_manage` | Whether current user may manage Formkit `( $allowed, $user_id )` |
| `we_formkit_upload_allowed_mimes` | Allowed MIME list for an upload field `( $mimes, $field )` |
| `we_formkit_private_storage_dir` | Absolute private storage base dir `( $path, $subdir )` — `$subdir` like `we-formkit-uploads` |

### Filters — Save & Resume

| Hook | When |
|------|------|
| `we_formkit_draft_ttl_days` | Allowed TTL options (days) for builder UI + validation `( $days )` — default `7,14,30,60,90`; clamped 1–365 |
| `we_formkit_form_draft_ttl_days` | Effective TTL for one form after stored meta `( $days, $form_id )` |
| `we_formkit_form_save_min_filled` | Minimum filled fields before Save unlocks `( $min, $form_id )` — `0` = always |
| `we_formkit_draft_mail_cooldown` | Seconds between resume emails for same email+form+IP `( $seconds )` — default `300`; clamped 30–3600 |
| `we_formkit_draft_max_store` | Soft cap for drafts in the options store `( $max )` — default `200`; clamped 20–5000 |
| `we_formkit_draft_reminder_lead_days` | Default days before expiry for opt-in calendar reminder `( $lead, $ttl_days )` |
| `we_formkit_draft_reminder_lead_options` | Dropdown choices for days before expiry `( $days, $ttl_days )` |
| `we_formkit_form_reminders_allowed` | Whether calendar (.ics) opt-in is allowed `( $allowed, $form_id )` |

### Filters — design / fields

| Hook | When |
|------|------|
| `we_formkit_color_schemes` | Add/replace named color schemes `( $schemes )` — slug => `{ label, colors }`; reserved: `theme`, `custom` |
| `we_formkit_form_style_colors` | Resolved form CSS colors `( $colors, $form_id, $stored )` |
| `we_formkit_repeater_item_types` | Allowed nested types inside a repeater group |
| `we_formkit_repeater_item_required` | Required message for a repeater item |
| `we_formkit_repeater_item_invalid` | Invalid message for a repeater item |

### Examples

```php
// Extra TTL choices in the builder (and validation).
add_filter( 'we_formkit_draft_ttl_days', static function () {
	return array( 14, 30, 60, 180 );
} );

// Force 30 days for one form regardless of UI.
add_filter( 'we_formkit_form_draft_ttl_days', static function ( $days, $form_id ) {
	return 123 === (int) $form_id ? 30 : $days;
}, 10, 2 );

// Mutate values before the entry is stored.
add_filter(
	'we_formkit_pre_submission_data',
	static function ( $data, $schema, $form_id ) {
		$data['processed_at'] = gmdate( 'c' );
		return $data;
	},
	10,
	3
);

// Change confirmation message / redirect after merge.
add_filter(
	'we_formkit_confirmation',
	static function ( $confirmation, $submission_id ) {
		$confirmation['message'] .= "\n\n#" . (int) $submission_id;
		return $confirmation;
	},
	10,
	2
);

// Add payload keys the frontend can read.
add_filter(
	'we_formkit_submit_response',
	static function ( $response, $submission_id ) {
		$response['entry_id'] = (int) $submission_id;
		return $response;
	},
	10,
	2
);

// Format a field for email / admin / export.
add_filter(
	'we_formkit_format_field_value',
	static function ( $display, $value, $field, $context ) {
		if ( 'email' === $context && isset( $field['type'] ) && 'tel' === $field['type'] ) {
			return esc_html( preg_replace( '/\s+/', '', (string) $value ) );
		}
		return $display;
	},
	10,
	4
);

// Extra spam check (modules can do the same via we_formkit_spam_check).
add_filter(
	'we_formkit_spam_check',
	static function ( $result, $data, $schema, $form_id ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Return new WP_Error( 'we_formkit_spam', '…', array( 'status' => 422 ) ) to reject.
		return $result;
	},
	10,
	4
);

// Custom smart tag in the picker + values.
add_filter(
	'we_formkit_merge_tag_catalog',
	static function ( $items ) {
		$items[] = array(
			'tag'         => '{custom_ref}',
			'label'       => 'Custom reference',
			'group'       => 'entry',
			'group_label' => 'Entry',
		);
		return $items;
	}
);
add_filter(
	'we_formkit_merge_vars',
	static function ( $vars, $submission_id ) {
		$vars['custom_ref'] = 'REF-' . (int) $submission_id;
		return $vars;
	},
	10,
	2
);

// Add a named scheme (shows next to Formkit Teal, etc.).
add_filter( 'we_formkit_color_schemes', static function ( $schemes ) {
	$schemes['brand-x'] = array(
		'label'  => 'Brand X',
		'colors' => array(
			'accent'      => '#1a4d8c',
			'accent_soft' => '#e8f0fa',
			'surface'     => '#ffffff',
			'bg'          => '#f5f7fa',
			'ink'         => '#1a1a1a',
			'muted'       => '#5a6570',
			'line'        => '#c8d0d8',
			'input'       => '#ffffff',
			'on_accent'   => '#ffffff',
			'danger'      => '#9b2c2c',
		),
	);
	return $schemes;
} );
```

### Smart / merge tags

Insert via the **Insert smart tag** picker (Notifications + Confirmations), or type `{tag}` manually.

| Group | Tags |
|-------|------|
| Form | `{form_title}`, `{form_id}` |
| Entry | `{submission_id}`, `{submission_url}`, `{date}`, `{all_fields}`, `{info_links}` (email) |
| Source | `{source_url}`, `{referrer}`, `{user_agent}` |
| Site | `{site_name}`, `{admin_email}` |
| User | `{user_login}`, `{user_email}`, `{user_display_name}` (empty for guests; snapshotted at submit for resend) |
| Fields | `{field:FIELD_ID}` |

Also available in notification templates: `{footer}` (notification footer fragment). No plain-IP tag (privacy — optional IP hash only).

- **Emails:** HTML body (WYSIWYG); subject uses plain-text values. Outbound mail is `text/html` with inline styles.
- **Confirmations:** message + redirect URL are merged on submit (plain text).
- Unknown `{…}` tags are left unchanged (whitelist replace).

### Info documents

Form meta `_wek_form_info_documents`: Media Library files with optional conditionals (`when` uses the same rule shape as field `show_when`). Delivery flags: `show_download` (confirmation + `{info_links}`), `attach_to_email` (+ optional `notification_ids`). Matching is deduped by attachment ID / filesystem path.

## REST

Public submit endpoint: `POST /wp-json/we-formkit/v1/submit`

- JSON body: `{ nonce, form_id, token?, _wek_started, website_url, values }`
- Multipart: same fields plus file inputs named by field id (use `id[]` for multiple)

Nonce: WordPress REST cookie nonce (`wp_rest`), sent as `X-WP-Nonce` and in the body.

## Naming

| Concern | Value |
|---------|--------|
| Text domain | `we-formkit` |
| CPT form / submission | `wek_form` / `wek_submission` |
| Meta prefix | `_wek_*` |
| Hooks | `we_formkit_*` |

## Frontend query params

| Param | Purpose |
|-------|---------|
| `wek_page` | Multipage step (1-based). Updated via `history.replaceState` while navigating; refresh restores that step. |
| `wek_page_form` | Form post ID when several multipage forms share the page (scopes `wek_page`). |
| `wek_form` + `token` | Secret embed routing (form **slug** + token). Do not reuse for multipage. |
| `wek_autofill` / `wek_autosubmit` | Cap-gated smoke helpers (`=1`). Autofill fills every reachable control: all choice/matrix answers, **custom** checkboxes/matrix rows (add buttons), extra repeater row, signature stroke, upload blob, etc. Autosubmit waits for the timing spam window, jumps to the last multipage step, then submits. |

## Deferred modules

Gravity Forms JSON import and AI PDF assist are intentionally out of core. Prefer separate plugins that register via `we_formkit_register_modules`.

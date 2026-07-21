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

## Register a module

```php
add_action(
	'we_formkit_register_modules',
	static function ( \Webentwicklerin\WeFormkit\Module_Registry $modules ) {
		$modules->add(
			'example',
			array(
				'name'        => 'Example Module',
				'description' => 'Demonstrates the module API.',
				'version'     => '1.0.0',
				'supports'    => array( 'importer' ),
				'bootstrap'   => static function () {
					// Hook REST routes, admin tabs, field types, etc.
				},
			)
		);
	}
);
```

## Useful hooks

### Extension points

| Hook | Type | When |
|------|------|------|
| `we_formkit_register_field_types` | action | After core field types are registered `( $registry )` |
| `we_formkit_register_modules` | action | During module bootstrap `( $modules )` |
| `we_formkit_module_registered` | action | After each module is added `( $id, $definition )` |
| `we_formkit_submission_created` | action | After a submission is stored `( $submission_id, $context )` |

### Filters

| Hook | When |
|------|------|
| `we_formkit_draft_ttl_days` | Allowed Save & Resume TTL options (days) for builder UI + validation `( $days )` — default `7,14,30,60,90`; clamped 1–365 |
| `we_formkit_form_draft_ttl_days` | Effective TTL for one form after stored meta `( $days, $form_id )` |
| `we_formkit_form_save_min_filled` | Minimum filled fields before Save unlocks `( $min, $form_id )` — `0` = always |
| `we_formkit_draft_reminder_lead_days` | Default days before expiry for opt-in calendar reminder `( $lead, $ttl_days )` |
| `we_formkit_draft_reminder_lead_options` | Dropdown choices for days before expiry `( $days, $ttl_days )` |
| `we_formkit_form_reminders_allowed` | Whether calendar (.ics) opt-in is allowed `( $allowed, $form_id )` |
| `we_formkit_resume_mail` | Resume email before send `( $mail, … )` — keys include optional `attachments` for .ics |
| `we_formkit_color_schemes` | Add/replace named color schemes `( $schemes )` — slug => `{ label, colors }`; reserved: `theme`, `custom` |
| `we_formkit_form_style_colors` | Resolved form CSS colors `( $colors, $form_id, $stored )` |
| `we_formkit_repeater_item_types` | Allowed nested types inside a repeater group |
| `we_formkit_repeater_item_required` | Required message for a repeater item |
| `we_formkit_repeater_item_invalid` | Invalid message for a repeater item |
| `we_formkit_notification_mail` | Notification mail args before `wp_mail` `( $mail, $notification, $submission_id, $form_id )` |

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

### Notification merge tags

Usable in subject, header, message, and footer: `{all_fields}`, `{form_title}`, `{submission_url}`, `{submission_id}`, `{form_id}`, `{date}`, `{site_name}`, `{admin_email}`, `{field:FIELD_ID}`, `{footer}`, `{info_links}` (matched info documents with download enabled). Notification bodies are HTML (WYSIWYG); outbound mail is `text/html` with inline styles.

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

## Deferred modules

Gravity Forms JSON import and AI PDF assist are intentionally out of core. Prefer separate plugins that register via `we_formkit_register_modules`.

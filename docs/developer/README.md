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

| Hook | When |
|------|------|
| `we_formkit_register_field_types` | After core field types are registered |
| `we_formkit_repeater_item_types` | Filter allowed nested types inside a repeater group |
| `we_formkit_form_style_colors` | Filter resolved form CSS colors `( $colors, $form_id, $stored )` |
| `we_formkit_register_modules` | During module bootstrap |
| `we_formkit_module_registered` | After each module is added |
| `we_formkit_submission_created` | After a submission is stored `( $submission_id, $context )` |
| `we_formkit_notification_mail` | Filter mail args before `wp_mail` `( $mail, $notification, $submission_id, $form_id )` |

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

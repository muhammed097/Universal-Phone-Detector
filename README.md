# Universal Phone Input for WordPress

WordPress plugin that automatically applies an international phone input with auto country detection to ALL forms across the site (native HTML forms, Elementor Forms, WPForms, Contact Form 7, Gravity Forms, Fluent Forms, Forminator, etc.).

## 🚀 How It Works

1.  **Auto-Discovery**:
    When a page loads, the plugin's JavaScript (`universal-iti-init.js`) scans the DOM for:
    -   Inputs with `type="tel"`
    -   Expected "phone-like" text inputs (inputs with names/IDs containing "phone", "mobile", "whatsapp", or with `autocomplete="tel"`).
    
    It also uses a **MutationObserver** to watch for new forms that appear dynamically (like Elementor Popups or AJAX multi-step forms) and initializes them instantly.

2.  **Country Detection (GeoIP)**:
    To automatically select the user's country flag:
    -   The browser calls our custom **WordPress REST API endpoint** (`/wp-json/universal-phone/v1/geo`).
    -   This endpoint checks the user's IP Address.
    -   It returns a simple country code (e.g., `"US"`, `"IN"`, `"GB"`).
    -   The plugin then sets the phone input's initial country to match.

3.  **Data Standardization**:
    -   The plugin creates a **hidden input field** next to every phone field.
    -   **Format**: `fieldname_e164` (e.g., if your field is named `billing_phone`, the hidden field is `billing_phone_e164`).
    -   **Value**: As the user types, this hidden field is automatically updated with the full international format (E.164), e.g., `+12125550123`.
    -   On form submission, this standardized value is sent to the server.

---

## 🌐 External APIs Used & Third-Party Services

**Yes, this plugin relies on external services for its default "Auto Country" feature.**

**Primary API**: `https://ipapi.co/json/` (HTTPS)
**Fallback API**: `http://ip-api.com/json/` (HTTP)

### 🔒 Privacy & Performance Features
1.  **Server-Side Proxy**: The request to these APIs is made by your **WordPress Server**, not the user's browser. This avoids Cross-Origin (CORS) issues and gives you control.
2.  **Smart Caching**: Results are strictly cached in WordPress Transients for **24 hours**.
    -   *Example*: If a user visits 10 pages, the external API is called only **once**. Subsequent checks use the cached value.
    -   This prevents hitting the rate limits of the free API providers.
3.  **Customizable**: If you have high traffic (>1000 visitors/day), you can use the provided filter `universal_phone_custom_geo_provider` to switch to a paid service (like MaxMind or a premium API key) easily.

---

## Installation
1.  Download the `universal-phone` folder.
2.  Upload it to your WordPress site's `wp-content/plugins/` directory.
3.  Activate the plugin via the **Plugins** menu in WordPress.

## Configuration & Filters

### 1. Default Fallback Country
If GeoIP fails (e.g., user hits rate limit), the plugin defaults to the user's browser language. If that fails, it defaults to 'US'. Change this via filter:

```php
add_filter( 'universal_phone_default_country', function( $country_code ) {
    return 'in'; // Set default to India
} );
```

### 2. Overwrite Original Input (Optional)
By default, the plugin creates a *separate* hidden field for the standardized number. To force the visible input to update to the E.164 format on form submission:

```php
add_filter( 'universal_phone_overwrite_input', '__return_true' );
```

### 3. Custom GeoIP Provider (Recommended for High Traffic)
The default free APIs have rate limits. Use this filter to implement your own logic or paid key:

```php
add_filter( 'universal_phone_custom_geo_provider', function( $false, $ip ) {
    // Example: Call your own MaxMind database or paid API
    // return 'FR'; // Return 2-letter country code
    return false; // Return false to keep using default logic
}, 10, 2 );
```

## Testing

### Basic HTML Form
Create a new page/post and add a custom HTML block:
```html
<form action="#" method="post">
    <label for="phone">Phone Number:</label>
    <input type="tel" id="phone" name="phone">
    <button type="submit">Submit</button>
</form>
```
**Verification steps:**
1.  Open the page.
2.  Confirm the flag matches your current country (GeoIP working).
3.  Inspect the element (Right Click -> Inspect).
4.  Find the hidden input `<input type="hidden" name="phone_e164" ...>`.
5.  Type a number and watch the hidden input value update.

---

## License & Credits

### License
This plugin is released under the **GNU General Public License v2 or later**.

### Credits
*   **International Telephone Input**: This plugin uses the [intl-tel-input](https://github.com/jackocnr/intl-tel-input) library by Jack O'Connor, which is licensed under the MIT License.
*   **GeoIP Services**: The default configuration uses the free tiers of:
    *   [ipapi.co](https://ipapi.co/)
    *   [ip-api.com](https://ip-api.com/)
    *   *Note: These services are provided as a convenience. They have rate limits. For commercial or high-traffic projects, you should configure a paid provider using the available filters.*

### Disclaimer
This software is provided "as is", without warranty of any kind. Please ensure you comply with the terms of service of any third-party APIs used in your final deployment.

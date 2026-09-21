# AI Vision Providers Comparison & Extension Guide

This guide provides an objective analysis of multimodal AI models for visual artwork analysis, comparing Google Gemini with alternative providers, and documents how to add new provider adapters to the plugin.

---

## 1. Provider Comparison Matrix

| Provider & Model | Vision Quality & Nuance | TTFT (Time to First Token) | Streaming Speed | Free Tier / Cost | Best For |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Google Gemini 3.5 Flash** *(Default)* |  Excellent (composition, colors, broad art history) | **~200–350 ms** (Fastest) | **~80 tokens/sec** |  **Free tier** (15 RPM / 1M TPM) | Real-time web streaming, high throughput, zero running cost. |
| **Google Gemini 2.5 Pro** |  Outstanding (deep iconographic analysis, subtle techniques) | ~800–1200 ms | ~45 tokens/sec | Free tier (2 RPM) / Low cost | In-depth museum-grade academic critiques where 1s initial latency is acceptable. |
| **Anthropic Claude 3.5 Sonnet** |  **Superior** (unmatched poetic prose, art history vocabulary, emotional depth) | ~400–600 ms | ~60 tokens/sec |  Paid only ($3 / $15 per MTok) | The most expressive, "human-like" art critic tone. |
| **OpenAI GPT-4o** |  Very Good (precise identification of famous artists, styles) | ~300–500 ms | ~70 tokens/sec |  Paid only ($2.50 / $10 per MTok) | Reliable, standard OpenAI SDK compatibility. |
| **OpenAI GPT-4o-mini** |  Moderate (good for simple style tags, less nuanced critique) | ~200–300 ms | ~85 tokens/sec |  Ultra low cost ($0.15 / $0.60 per MTok) | Budget high-volume environments. |
| **Pixtral 12B / LLaVA (Self-Hosted)** |  Fair to Good (depends on quantization and prompt) | ~1000–3000 ms (hardware dependent) | ~20–40 tokens/sec |  100% Free (requires GPU server) | Strict data privacy, zero external cloud dependency. |

---

## 2. In-Depth Analysis: Is There a Better Provider Than Gemini?

### Why Google Gemini was chosen as the primary provider:
1. **Multimodal Native Design**: Gemini was trained natively on multimodal audio/video/image tokens rather than having an external vision encoder grafted onto a text LLM. It interprets brush strokes, palettes, lighting, and textures with exceptional clarity.
2. **Streaming Latency**: Gemini Flash delivers tokens in under 300ms, essential for web visitors who expect immediate feedback after submitting a form.
3. **Accessibility & Free Tier**: Google AI Studio provides 15 requests per minute completely free, making it immediately viable for portfolio sites, art galleries, and students without upfront cloud commitments.

### When to Consider Anthropic Claude 3.5 Sonnet:
If your priority is **maximum literary elegance and art criticism depth**:
- Claude 3.5 Sonnet produces the most evocative, lyrical art critique with sophisticated terminology (e.g. chiaroscuro, impasto, pentimento, spatial tension) and nuanced emotional resonance.
- *Downside*: Requires a paid Anthropic account and credits upfront.

### When to Consider OpenAI GPT-4o:
- If your agency or organization already standardizes on OpenAI API keys and enterprise agreements.

---

## 3. How the Plugin is Architected for Multi-Provider Switching

The plugin's database schema already defines a `provider` column:
```sql
provider varchar(32) NOT NULL DEFAULT 'gemini'
```
And settings store:
```php
'ai_provider' => 'gemini' // expandable to 'openai', 'claude'
```

### Adding an OpenAI or Claude Provider Adapter

To add another provider (e.g. OpenAI GPT-4o):

1. **Create Adapter Class**:
   Create `includes/class-openai-client.php`:
   ```php
   <?php
   defined('ABSPATH') || exit;

   class WPA_Art_Analyzer_OpenAI_Client {
       public static function stream_critique($image_path, $prompt, $on_chunk) {
           $api_key = WPA_Art_Analyzer_Settings::get('openai_api_key');
           $base64 = base64_encode(file_get_contents($image_path));

           $payload = json_encode([
               'model' => 'gpt-4o',
               'stream' => true,
               'messages' => [
                   [
                       'role' => 'user',
                       'content' => [
                           ['type' => 'text', 'text' => $prompt],
                           ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$base64}"]]
                       ]
                   ]
               ]
           ]);

           $ch = curl_init('https://api.openai.com/v1/chat/completions');
           curl_setopt_array($ch, [
               CURLOPT_POST => true,
               CURLOPT_POSTFIELDS => $payload,
               CURLOPT_HTTPHEADER => [
                   'Content-Type: application/json',
                   'Authorization: Bearer ' . $api_key,
               ],
               CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($on_chunk) {
                   // Parse OpenAI "data: {...}" SSE lines and invoke $on_chunk($text)
                   return strlen($chunk);
               }
           ]);
           curl_exec($ch);
           curl_close($ch);
       }
   }
   ```

2. **Route in Controller**:
   In `includes/class-rest-controller.php`, inspect `WPA_Art_Analyzer_Settings::get('ai_provider')`:
   ```php
   $provider = WPA_Art_Analyzer_Settings::get('ai_provider', 'gemini');
   if ($provider === 'openai') {
       WPA_Art_Analyzer_OpenAI_Client::stream_critique(...);
   } else {
       WPA_Art_Analyzer_Gemini_Client::stream_critique(...);
   }
   ```

3. **Add Field in Admin View**:
   Add the provider dropdown and API key field in `admin/views/settings.php`.

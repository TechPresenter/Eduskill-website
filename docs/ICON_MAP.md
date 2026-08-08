# Icon System Reference — emoji → Lucide (project-wide)

**Goal:** ZERO emojis anywhere. Every emoji becomes a professional **Lucide** SVG icon.
Social icons use **Font Awesome brands** (already loaded). Auth social-login uses the
**official Google & Facebook brand SVGs** below.

## How to emit icons
- HTML/markup context → `<?= lucide('icon-name') ?>` (helper already exists in includes/helper.php; outputs `<i data-lucide="...">`, rendered to SVG on load).
- Social brand icon → `<i class="fab fa-facebook-f"></i>` etc., or use `social_fa($platform)`.
- Emoji stored inside a PHP **string/array value** (e.g. `'icon' => '📚'`): replace the value with the Lucide **name** string (`'icon' => 'book'`) AND make sure it is rendered with `lucide($icon)` (not echoed raw). If the surrounding code echoes it raw as an emoji, wrap the echo in `lucide(...)`.
- NEVER leave an emoji. NEVER print an emoji as a fallback.

## Rules
- Keep all PHP logic identical — only change icons/markup/styles.
- After editing a file, run `/c/xampp/php/php.exe -l <file>` and fix until "No syntax errors".
- Match existing size conventions; the CSS already sizes `svg.lucide`. In buttons use inline `lucide('x')`.
- Consistent stroke, size, color come from CSS — just use the right names.

## emoji → Lucide name (use these; pick a sensible Lucide name for anything not listed)
- 📊 bar-chart-3 · 📈 trending-up · 📉 trending-down · 🎯 target · 🏆 trophy · 🏅 award · ⭐ star · 🌟 sparkles · ✨ sparkles
- ⚙️ settings · 🎨 palette · 🧭 compass · 🖼️ image · 🧱 layout-grid · 📄 file-text · 📁 folder · 🗂️ folders · 🗃️ archive
- ✍️ pen-line · 📝 clipboard-pen · 📋 clipboard-list · 🏷️ tag · 🏷 tag · 💬 message-square · 📖 book-open · 📚 book · 📜 scroll
- 📸 camera · 🎬 clapperboard · 🎥 video · 📹 video · 📥 inbox · 📤 send · 📎 paperclip · 🔗 link · 📦 package
- 📅 calendar-days · 🗓️ calendar · 🕐 clock · ⏱ clock · ⏰ alarm-clock · 🎟️ ticket · 🎫 ticket
- 📣 megaphone · 📢 megaphone · 🔔 bell · 💡 lightbulb · 🔥 flame · ⚡ zap · 🚀 rocket
- 💰 coins · 🪙 coins · 💳 credit-card · 🧾 receipt · 💎 gem · 🎁 gift · 💵 banknote · ₹ indian-rupee
- 👥 users · 👤 user · 🙂 smile · 🙌 hand-heart · 🤝 handshake · 👋 hand · 👩 user · 👨 user · 🧑 user · 🪪 id-card
- 🎓 graduation-cap · 💼 briefcase · 🏛️ landmark · 🏫 school · 🏥 hospital · 🏠 home · 🏘️ home · 🌍 globe · 🌐 globe
- ❤ heart · ❤️ heart · 💚 heart · 💙 heart · 🩷 heart · 🤍 heart · 💖 heart
- ✅ circle-check · ✔ check · ✔️ check · ☑️ check-square · ❌ circle-x · ✖ x · ⚠️ triangle-alert · ⛔ ban · 🚫 ban
- 📍 map-pin · 🗺️ map · 🧭 compass · ✈️ plane · 🚗 car · 📌 pin
- 📧 mail · ✉️ mail · 📨 mail · 📬 mailbox · 📞 phone · ☎️ phone · ✆ phone · 📱 smartphone · 💻 laptop · 🖥️ monitor
- 🔍 search · 🔎 search · 🔐 shield · 🔒 lock · 🔓 lock-open · 🛡️ shield · 🗝️ key · 🔑 key
- ↪️ corner-up-right · ➡️ arrow-right · ⬅️ arrow-left · ⬆️ arrow-up · ⬇️ arrow-down · ↑ arrow-up · → arrow-right · ← arrow-left · › chevron-right
- 🪟 app-window · ❓ circle-help · ❔ circle-help · ℹ️ info · 💭 message-circle · 🗨️ message-square
- 🛠️ wrench · 🔧 wrench · 🔨 hammer · 💧 droplet · 🌱 sprout · 🌳 tree-pine · ♻️ recycle · 🩺 stethoscope · 💊 pill
- 🍎 apple · 🥗 salad · 🎉 party-popper · 🎈 party-popper · 👁 eye · 👁️ eye · 🤲 hand-heart · 🙏 heart-handshake

## Official brand SVGs (use ONLY for auth social login buttons)

**Google** (multicolor "G"):
```html
<svg class="brand-svg" width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303c-.792 2.237-2.231 4.166-4.087 5.571l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>
```

**Facebook** (blue "f"):
```html
<svg class="brand-svg" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
```

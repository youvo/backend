import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import mjml from 'mjml';

const { html: compiled, errors } = await mjml(readFileSync('src/layout.mjml', 'utf8'), {
  minify: true,
  validationLevel: 'strict',
});
if (errors.length) {
  throw new Error(errors.map((e) => e.formattedMessage).join('\n'));
}

// Every section has one full-width column, so this strips MJML output that is
// redundant or unsupported there. Every step must match, so that a change in
// the MJML output fails the build.
const strip = [
  // Responsive column width, redundant for one 100% column.
  [/<style type="text\/css">@media only screen and \(min-width:480px\)\{\.mj-column-per-100\{[^}]*\}\}<\/style>/, ''],
  [/<style media="screen and \(min-width:480px\)">\.moz-text-html \.mj-column-per-100\{[^}]*\}<\/style>/, ''],
  // Outlook link fix, only needed for mso-specific anchors.
  [/#outlook a\{padding:0\}/, ''],
  // MJML's paragraph margin; body paragraphs keep client defaults (Outlook
  // gets them in the layout).
  [/p\{display:block;margin:13px 0\}/, ''],
  // Unsupported in many clients and not needed for the logo.
  [/;?outline:none/, ''],
  // Only inline occurrences; the body area sets it once in the head style.
  [/(style="[^"]*?);?word-break:break-word/g, '$1'],
  // Centering is moot for 100% wide columns.
  [/(<td style="direction:ltr;font-size:0px;padding:[^";]*);text-align:center"/g, '$1"'],
  // Left alignment is inherited from the column.
  [/(<div style="font-family:[^"]*?);text-align:left/g, '$1'],
  // Full-width columns need no inline-block layout or left alignment.
  [/(class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px);text-align:left;direction:ltr;display:inline-block;vertical-align:top;(width:100%")/g, '$1;direction:ltr;$2'],
  // Merge the two head style blocks.
  [/<\/style>(<!--\[if mso\]>[\s\S]*?<!\[endif\]-->)<style type="text\/css">([^<]*)<\/style>/, '$2</style>$1'],
];
let html = compiled;
for (const [pattern, replacement] of strip) {
  if (!html.match(pattern)) {
    throw new Error(`Post-processing step did not match: ${pattern}`);
  }
  html = html.replace(pattern, replacement);
}

mkdirSync('dist', { recursive: true });
writeFileSync('dist/layout.html', html);

const twig = `{#
/**
 * @file
 * Compiled from mjml/src/layout.mjml. Do not edit; run "npm run build" in mjml/.
 */
#}
{% if is_html %}
${html}
{% else %}
{{ body }}
{% endif %}
`;
writeFileSync('../templates/email-wrap--mailer.html.twig', twig);

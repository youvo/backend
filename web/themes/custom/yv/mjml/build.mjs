import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import mjml from 'mjml';

const { html: compiled, errors } = await mjml(readFileSync('src/layout.mjml', 'utf8'), {
  minify: true,
  validationLevel: 'strict',
});
if (errors.length) {
  throw new Error(errors.map((e) => e.formattedMessage).join('\n'));
}

// The layout has a single full-width column, so MJML's responsive column
// rules and some resets only add CSS that email clients rate as unsupported.
// Every step must match, so that a change in the MJML output fails the build.
const strip = [
  // Responsive column width, redundant for one 100% column.
  [/<style type="text\/css">@media only screen and \(min-width:480px\)\{\.mj-column-per-100\{[^}]*\}\}<\/style>/, ''],
  [/<style media="screen and \(min-width:480px\)">\.moz-text-html \.mj-column-per-100\{[^}]*\}<\/style>/, ''],
  // Outlook link fix, only needed for mso-specific anchors.
  [/#outlook a\{padding:0\}/, ''],
  // Default paragraph margin, the body paragraphs are styled in the layout.
  [/p\{display:block;margin:13px 0\}/, ''],
  // Unsupported in many clients and not needed for the logo.
  [/;?outline:none/, ''],
  // Only inline occurrences; the body area sets it once in the head style.
  [/(style="[^"]*?);?word-break:break-word/g, '$1'],
  // Section cells center their columns, but every column is 100% wide.
  [/(<td style="direction:ltr;font-size:0px;padding:[^";]*);text-align:center"/g, '$1"'],
  // Text blocks repeat the left alignment their column already sets.
  [/(<div style="font-family:[^"]*?);text-align:left/g, '$1'],
  // Columns are inline blocks so that several share a row; each column here
  // is a full-width block, left aligned by default.
  [/(class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px);text-align:left;direction:ltr;display:inline-block;vertical-align:top;(width:100%")/g, '$1;direction:ltr;$2'],
  // One style block in the head instead of two.
  [/<\/style>(<!--\[if mso\]>[\s\S]*?<!\[endif\]-->)<style type="text\/css">([^<]*)<\/style>/, '$2</style>$1'],
];
let html = compiled;
for (const [pattern, replacement] of strip) {
  if (!html.match(pattern)) {
    throw new Error(`Post-processing step did not match: ${pattern}`);
  }
  html = html.replace(pattern, replacement);
}

mkdirSync('build', { recursive: true });
writeFileSync('build/layout.html', html);

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

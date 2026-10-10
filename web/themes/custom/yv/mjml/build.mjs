import { readFileSync, writeFileSync } from 'node:fs';

const html = readFileSync('build/layout.html', 'utf8');
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

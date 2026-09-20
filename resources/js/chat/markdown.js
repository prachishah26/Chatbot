/**
 * Renders assistant replies as safe HTML.
 *
 * The source is HTML-escaped up front, so every later pass works on inert text
 * and can never reintroduce markup from the model. Only the tags built here
 * reach the DOM, and link targets are restricted to safe schemes.
 */

const ESCAPES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

export const escapeHtml = (text) =>
    String(text ?? '').replace(/[&<>"']/g, (char) => ESCAPES[char]);

// Private-use code points stand in for extracted spans while blocks are parsed.
const FENCE_MARK = '';
const CODE_MARK = '';

const FENCE = /```([\w+#.-]*)[ \t]*\r?\n?([\s\S]*?)```/g;
const HEADING = /^(#{1,6})\s+(.*)$/;
const RULE = /^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/;
// Escaping runs first, so a blockquote marker reaches this pass as "&gt;".
const QUOTE = /^\s*&gt;\s?(.*)$/;
const BULLET = /^(\s*)[-*+]\s+(.*)$/;
const NUMBER = /^(\s*)\d+[.)]\s+(.*)$/;
const ROW = /^\s*\|(.+)\|\s*$/;
const DIVIDER = /^\s*\|?[\s:|-]*-[\s:|-]*\|?\s*$/;
const PLACEHOLDER = new RegExp(`^${FENCE_MARK}(\\d+)${FENCE_MARK}$`);
const SAFE_HREF = /^(?:https?:\/\/|mailto:|\/)/i;

/**
 * @param {string} raw Untrusted markdown, typically straight from the model.
 * @returns {string} HTML safe to assign to innerHTML.
 */
export function renderMarkdown(raw) {
    const fences = [];

    const text = escapeHtml(raw).replace(FENCE, (_, language, code) => {
        fences.push(codeBlock(language, code));

        return `\n${FENCE_MARK}${fences.length - 1}${FENCE_MARK}\n`;
    });

    return blocks(text.split(/\r?\n/), fences).join('');
}

/**
 * Walks the document line by line, emitting one HTML block at a time.
 */
function blocks(lines, fences) {
    const html = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];

        if (line.trim() === '') {
            index++;
            continue;
        }

        const placeholder = line.trim().match(PLACEHOLDER);

        if (placeholder) {
            html.push(fences[Number(placeholder[1])]);
            index++;
            continue;
        }

        if (RULE.test(line)) {
            html.push('<hr>');
            index++;
            continue;
        }

        const heading = line.match(HEADING);

        if (heading) {
            const level = heading[1].length;
            html.push(`<h${level}>${inline(heading[2])}</h${level}>`);
            index++;
            continue;
        }

        if (ROW.test(line) && DIVIDER.test(lines[index + 1] ?? '')) {
            index = table(lines, index, html);
            continue;
        }

        if (QUOTE.test(line)) {
            index = quote(lines, index, html, fences);
            continue;
        }

        if (BULLET.test(line) || NUMBER.test(line)) {
            index = list(lines, index, html);
            continue;
        }

        index = paragraph(lines, index, html);
    }

    return html;
}

/**
 * Consumes consecutive list items, nesting anything indented two or more spaces.
 */
function list(lines, start, html) {
    const ordered = NUMBER.test(lines[start]);
    const items = [];
    let index = start;

    while (index < lines.length) {
        const match = lines[index].match(ordered ? NUMBER : BULLET);

        if (!match) {
            const line = lines[index];
            const startsOtherList = (ordered ? BULLET : NUMBER).test(line);

            // A plain indented line under an item continues that item's text.
            if (!startsOtherList && line.trim() !== '' && items.length > 0 && /^\s/.test(line)) {
                items[items.length - 1].text += ` ${line.trim()}`;
                index++;
                continue;
            }

            break;
        }

        items.push({
            depth: Math.floor(match[1].replace(/\t/g, '  ').length / 2),
            text: match[2],
        });
        index++;
    }

    html.push(renderItems(items, ordered));

    return index;
}

function renderItems(items, ordered) {
    const tag = ordered ? 'ol' : 'ul';
    const parts = [`<${tag}>`];
    let depth = 0;

    items.forEach((item) => {
        const level = Math.min(item.depth, depth + 1);

        while (depth < level) {
            parts.push(`<${tag}>`);
            depth++;
        }

        while (depth > level) {
            parts.push(`</${tag}>`);
            depth--;
        }

        parts.push(`<li>${inline(item.text)}</li>`);
    });

    while (depth > 0) {
        parts.push(`</${tag}>`);
        depth--;
    }

    parts.push(`</${tag}>`);

    return parts.join('');
}

function quote(lines, start, html, fences) {
    const inner = [];
    let index = start;

    while (index < lines.length && QUOTE.test(lines[index])) {
        inner.push(lines[index].match(QUOTE)[1]);
        index++;
    }

    html.push(`<blockquote>${blocks(inner, fences).join('')}</blockquote>`);

    return index;
}

function table(lines, start, html) {
    const cells = (line) =>
        line
            .trim()
            .replace(/^\||\|$/g, '')
            .split('|')
            .map((cell) => cell.trim());

    const head = cells(lines[start]);
    const body = [];
    let index = start + 2;

    while (index < lines.length && ROW.test(lines[index])) {
        body.push(cells(lines[index]));
        index++;
    }

    const headHtml = head.map((cell) => `<th>${inline(cell)}</th>`).join('');
    const bodyHtml = body
        .map((row) => `<tr>${row.map((cell) => `<td>${inline(cell)}</td>`).join('')}</tr>`)
        .join('');

    html.push(
        `<div class="md-table"><table><thead><tr>${headHtml}</tr></thead><tbody>${bodyHtml}</tbody></table></div>`,
    );

    return index;
}

function paragraph(lines, start, html) {
    const collected = [];
    let index = start;

    while (index < lines.length) {
        const line = lines[index];

        if (
            line.trim() === '' ||
            RULE.test(line) ||
            HEADING.test(line) ||
            QUOTE.test(line) ||
            BULLET.test(line) ||
            NUMBER.test(line) ||
            PLACEHOLDER.test(line.trim())
        ) {
            break;
        }

        collected.push(line.trim());
        index++;
    }

    html.push(`<p>${inline(collected.join('\n')).replace(/\n/g, '<br>')}</p>`);

    return index;
}

/**
 * Applies span-level markup. Inline code is lifted out first so its contents
 * are never treated as emphasis.
 */
function inline(text) {
    const codes = [];

    let html = text.replace(/`([^`\n]+)`/g, (_, code) => {
        codes.push(code);

        return `${CODE_MARK}${codes.length - 1}${CODE_MARK}`;
    });

    html = html
        .replace(/!?\[([^\]\n]*)\]\(([^)\s]+)\)/g, (match, label, href) => link(label, href, match))
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_]+)__/g, '<strong>$1</strong>')
        .replace(/(^|[\s(])\*([^*\n]+)\*/g, '$1<em>$2</em>')
        .replace(/(^|[\s(])_([^_\n]+)_/g, '$1<em>$2</em>')
        .replace(/~~([^~]+)~~/g, '<del>$1</del>');

    return html.replace(
        new RegExp(`${CODE_MARK}(\\d+)${CODE_MARK}`, 'g'),
        (_, index) => `<code>${codes[Number(index)]}</code>`,
    );
}

/**
 * Only well-known schemes become links; anything else degrades to its label.
 */
function link(label, href, original) {
    if (!SAFE_HREF.test(href)) {
        return original;
    }

    return `<a href="${href}" target="_blank" rel="noopener noreferrer nofollow">${label || href}</a>`;
}

function codeBlock(language, code) {
    const label = language || 'code';

    return `<div class="md-code" data-code-block>
        <div class="md-code-head"><span>${escapeHtml(label)}</span><button type="button" data-copy-code aria-label="Copy code">Copy</button></div>
        <pre><code>${code.replace(/\s+$/, '')}</code></pre>
    </div>`;
}

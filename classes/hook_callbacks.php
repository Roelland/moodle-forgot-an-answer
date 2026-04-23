<?php
namespace local_forgotananswer;

/**
 * Hook callbacks for Forgot an Answer plugin.
 *
 * @package    local_forgotananswer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Inject the answer-check script into the footer of quiz attempt pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $PAGE, $USER;

        // Only inject on quiz attempt pages.
        if ($PAGE->pagetype !== 'mod-quiz-attempt') {
            return;
        }

        // Role filter: if any roles are configured, only inject for users who hold one of them.
        $enabledroles = get_config('local_forgotananswer', 'enabled_roles');
        if (!empty($enabledroles)) {
            $roleids = array_filter(explode(',', $enabledroles));
            if (!empty($roleids)) {
                // get_user_roles with $checkparentcontexts=true walks up to system context,
                // so a teacher enrolled at course level is still found here.
                $userroles    = get_user_roles($PAGE->context, $USER->id, true);
                $userroleids  = array_column($userroles, 'roleid');
                if (empty(array_intersect($userroleids, $roleids))) {
                    return;
                }
            }
        }

        $hook->add_html(self::render_script());
    }

    /**
     * Returns the inline script that intercepts the Next button and validates answers.
     */
    private static function render_script(): string {
        $str = json_encode([
            'title'    => get_string('modal_title',         'local_forgotananswer'),
            'single'   => get_string('modal_body_single',   'local_forgotananswer'),
            'multiple' => get_string('modal_body_multiple', 'local_forgotananswer'),
            'question' => get_string('modal_question',      'local_forgotananswer'),
            'ok'       => get_string('modal_ok',            'local_forgotananswer'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
        <script>
        (function () {
            'use strict';
            var STR = $str;

            /**
             * Decide whether a single .que block has been answered.
             * Returns true for question types we cannot inspect (unknown markup).
             */
            function isAnswered(que) {
                // Descriptive / informational blocks are never required.
                if (
                    que.classList.contains('description') ||
                    que.classList.contains('informationitem')
                ) {
                    return true;
                }

                // Scope checks to the answer area where possible.
                var scope = que.querySelector('.answer') || que;

                // --- Radio buttons (multichoice single, true/false) ---
                var radios = scope.querySelectorAll('input[type="radio"]');
                if (radios.length > 0) {
                    return Array.prototype.some.call(radios, function (r) { return r.checked; });
                }

                // --- Checkboxes (multiple-response multichoice) ---
                var checkboxes = scope.querySelectorAll('input[type="checkbox"]');
                if (checkboxes.length > 0) {
                    return Array.prototype.some.call(checkboxes, function (c) { return c.checked; });
                }

                // --- Text / number inputs (shortanswer, numerical) ---
                var texts = scope.querySelectorAll('input[type="text"], input[type="number"]');
                if (texts.length > 0) {
                    return Array.prototype.every.call(texts, function (t) {
                        return t.value.trim() !== '';
                    });
                }

                // --- Textareas (essay) — also try the TinyMCE API ---
                var textareas = scope.querySelectorAll('textarea');
                if (textareas.length > 0) {
                    for (var i = 0; i < textareas.length; i++) {
                        var ta = textareas[i];
                        var content = ta.value.trim();
                        if (
                            typeof tinymce !== 'undefined' &&
                            ta.id &&
                            tinymce.get(ta.id)
                        ) {
                            content = tinymce.get(ta.id).getContent({ format: 'text' }).trim();
                        }
                        if (content === '') {
                            return false;
                        }
                    }
                    return true;
                }

                // --- Select menus (matching, select-missing-words) ---
                var selects = scope.querySelectorAll('select');
                if (selects.length > 0) {
                    return Array.prototype.every.call(selects, function (s) {
                        return s.value !== '' && s.selectedIndex > 0;
                    });
                }

                // Unknown question type — do not block.
                return true;
            }

            /**
             * Returns the display label for a question (e.g. "5" or "Question 5").
             * Falls back to the 1-based position in the .que list.
             */
            function getQuestionLabel(que, position) {
                var qno = que.querySelector('.info .no .qno');
                if (qno) {
                    return qno.textContent.trim();
                }
                // Fallback: use the whole .no heading text, or the position index.
                var no = que.querySelector('.info .no');
                if (no) {
                    return no.textContent.trim();
                }
                return String(position);
            }

            /**
             * Returns an array of labels for every unanswered question on the page.
             * An empty array means all questions are answered.
             */
            function unansweredLabels() {
                var missed = [];
                var questions = document.querySelectorAll('.que');
                var position = 0;
                for (var i = 0; i < questions.length; i++) {
                    var que = questions[i];
                    if (
                        que.classList.contains('description') ||
                        que.classList.contains('informationitem')
                    ) {
                        continue;
                    }
                    position++;
                    if (!isAnswered(que)) {
                        missed.push(getQuestionLabel(que, position));
                    }
                }
                return missed;
            }

            function formatList(missed) {
                if (missed.length === 1) {
                    return STR.question + ' ' + missed[0];
                }
                if (missed.length === 2) {
                    return STR.question + ' ' + missed[0] + ' &amp; ' + missed[1];
                }
                return STR.question + ' ' + missed.slice(0, -1).join(', ') + ', &amp; ' + missed[missed.length - 1];
            }

            function showModal(missed) {
                var existing = document.getElementById('faa-overlay');
                if (existing) {
                    existing.remove();
                }

                var isSingle = missed.length === 1;
                var title = STR.title;
                var body = (isSingle ? STR.single : STR.multiple) + ' ' + formatList(missed);

                var overlay = document.createElement('div');
                overlay.id = 'faa-overlay';
                overlay.style.cssText =
                    'position:fixed;top:0;right:0;bottom:0;left:0;' +
                    'background:rgba(0,0,0,.55);z-index:10000;' +
                    'display:flex;align-items:center;justify-content:center;';

                var box = document.createElement('div');
                box.style.cssText =
                    'background:#fff;border-radius:.5rem;padding:2rem;' +
                    'max-width:440px;width:90%;text-align:center;' +
                    'box-shadow:0 8px 32px rgba(0,0,0,.25);';
                box.innerHTML =
                    '<h4 style="margin-bottom:.75rem;font-size:1.25rem;">' +
                        title +
                    '</h4>' +
                    '<p style="color:#555;margin-bottom:1.5rem;">' +
                        body +
                    '</p>' +
                    '<button id="faa-ok" class="btn btn-primary px-4">' + STR.ok + '</button>';

                overlay.appendChild(box);
                document.body.appendChild(overlay);

                var ok = document.getElementById('faa-ok');
                ok.focus();

                function close() {
                    overlay.remove();
                }

                ok.addEventListener('click', close);
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) {
                        close();
                    }
                });
                document.addEventListener('keydown', function esc(e) {
                    if (e.key === 'Escape') {
                        close();
                        document.removeEventListener('keydown', esc);
                    }
                });
            }

            function init() {
                var btn = document.getElementById('mod_quiz-next-nav');
                if (!btn) {
                    return;
                }

                btn.addEventListener('click', function (e) {
                    var missed = unansweredLabels();
                    if (missed.length > 0) {
                        e.preventDefault();
                        showModal(missed);
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        HTML;
    }
}

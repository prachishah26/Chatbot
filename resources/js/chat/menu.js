/**
 * Closes any open <details> menu on an outside click or Escape.
 *
 * The menus work without this; it only adds the dismissal people expect.
 */
export function initMenus(root = document) {
    const closeAll = (except = null) => {
        root.querySelectorAll('[data-menu][open]').forEach((menu) => {
            if (menu !== except) {
                menu.open = false;
            }
        });
    };

    root.addEventListener('click', (event) => {
        closeAll(event.target.closest('[data-menu]'));
    });

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
}

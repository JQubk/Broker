document.addEventListener('DOMContentLoaded', function() {
    const grid = document.querySelector('.main-grid');
    
    if (grid) {
        grid.addEventListener('click', function(e) {
            const target = e.target;
            
            if (target.matches('[data-role="broker-action"]')) {
                e.preventDefault();
                
                const action = target.dataset.action;
                const id = target.dataset.id;
                
                if (action === 'delete' && confirm('Delete this item?')) {
                    handleDelete(id);
                }
            }
        });
    }
    
    function handleDelete(id) {
        console.log('Delete:', id);
    }
});

BX.ready(function() {
    if (typeof BX.SidePanel !== 'undefined') {
        const links = document.querySelectorAll('[data-broker-sidepanel="Y"]');
        
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                let url = this.getAttribute('href');
                if (url && typeof BX.util.add_url_param === 'function') {
                    url = BX.util.add_url_param(url, {
                        IFRAME: 'Y',
                        IFRAME_TYPE: 'SIDE_SLIDER'
                    });
                }
                
                BX.SidePanel.Instance.open(url, {
                    width: 1200,
                    cacheable: false
                });
            });
        });
    }
});
BX.ready(function () {
    var detailLinks = document.querySelectorAll('[data-broker-sidepanel="Y"]');
    detailLinks.forEach(function (linkElement) {
        linkElement.addEventListener('click', function (event) {
            event.preventDefault();

            var url = linkElement.getAttribute('href');
            if (!url) {
                return;
            }

            if (BX && BX.util && typeof BX.util.add_url_param === 'function') {
                url = BX.util.add_url_param(url, {
                    IFRAME: 'Y',
                    IFRAME_TYPE: 'SIDE_SLIDER'
                });
            }

            BX.SidePanel.Instance.open(url, {
                width: 1200,
                cacheable: false,
                allowChangeHistory: true
            });
        });
    });

    var commissionModalElement = document.getElementById('commissionRequestModal');
    if (commissionModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var commissionModal = new bootstrap.Modal(commissionModalElement);

        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-role="broker-commission-request"]');
            if (!target) {
                return;
            }

            event.preventDefault();

            var dealId = target.getAttribute('data-deal-id') || '';
            var commissionAmount = target.getAttribute('data-commission-amount') || '';

            var dealIdPlaceholder = commissionModalElement.querySelector('[data-broker-commission-deal-id]');
            var amountPlaceholder = commissionModalElement.querySelector('[data-broker-commission-amount]');

            if (dealIdPlaceholder) {
                dealIdPlaceholder.textContent = dealId;
            }

            if (amountPlaceholder) {
                amountPlaceholder.textContent = commissionAmount;
            }

            commissionModal.show();
        });
    }

    document.addEventListener('click', function (event) {
        var deleteButton = event.target.closest('[data-role="broker-deal-delete"]');
        if (!deleteButton) {
            return;
        }

        event.preventDefault();

        var rowId = deleteButton.getAttribute('data-row-id');
        if (!rowId) {
            return;
        }

        if (!confirm('Delete this deal from the list?')) {
            return;
        }

        if (!BX || !BX.Main || !BX.Main.gridManager) {
            return;
        }

        var grid = BX.Main.gridManager.getInstanceById('BROKER_DEALS_GRID');
        if (!grid || !grid.getRows) {
            return;
        }

        var row = grid.getRows().getById(rowId);
        if (row && typeof row.remove === 'function') {
            row.remove();
        }
    });

    var logoutButton = document.getElementById('broker-logout-button');
    
    if (logoutButton) {
        logoutButton.addEventListener('click', function (event) {
            event.preventDefault();

            var confirmed = confirm('Do you really want to log out?');
            if (!confirmed) {
                return;
            }

            var url = logoutButton.getAttribute('data-logout-url') || logoutButton.getAttribute('href');
            if (url) {
                window.location.href = url;
            }
        });
    }
});

@php
    $isCallcenterOrderForm = request()->routeIs('filament.admin.resources.callcenter.orders.create')
        || request()->routeIs('filament.admin.resources.callcenter.orders.edit');
    $isCallcenterOrdersList = request()->routeIs('filament.admin.resources.callcenter.orders.index');
    $isLogisticsOrdersList = request()->routeIs('filament.admin.resources.logistics.orders.index');
    $isKitchenTicketsList = request()->routeIs('filament.admin.resources.kitchen.kitchen-tickets.index');
@endphp

@if($isCallcenterOrdersList || $isLogisticsOrdersList || $isKitchenTicketsList)
    <style>
        .fi-resource-list-records-page .fi-ta,
        .fi-resource-list-records-page .fi-ta *:not(svg):not(path) {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .fi-resource-list-records-page .fi-ta .fi-ta-header-cell-label,
        .fi-resource-list-records-page .fi-ta .fi-input,
        .fi-resource-list-records-page .fi-ta input,
        .fi-resource-list-records-page .fi-ta textarea,
        .fi-resource-list-records-page .fi-ta select,
        .fi-resource-list-records-page .fi-ta .fi-badge,
        .fi-resource-list-records-page .fi-ta .fi-dropdown-list-item-label,
        .fi-resource-list-records-page .fi-ta .fi-ta-text,
        .fi-resource-list-records-page .fi-ta .fi-ta-text-item,
        .fi-resource-list-records-page .fi-ta .fi-ta-text-item-label,
        .fi-resource-list-records-page .fi-ta .fi-ta-summary-row-heading,
        .fi-resource-list-records-page .fi-ta td,
        .fi-resource-list-records-page .fi-ta th {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

            .callcenter-order-items-inline {
                display: grid;
                gap: 4px;
                min-width: 0;
                color: #334155;
                font-size: 11px;
                line-height: 1.2;
            }

            .callcenter-order-items-inline [x-cloak] {
                display: none !important;
            }

            .callcenter-order-items-inline-block {
                min-width: 0;
                padding-bottom: 4px;
                border-bottom: 1px solid #e2e8f0;
            }

            .callcenter-order-items-inline-block:last-of-type {
                padding-bottom: 0;
                border-bottom: 0;
            }

            .callcenter-order-items-inline-set-head,
            .callcenter-order-items-inline-regular-head {
                display: flex;
                width: 100%;
                min-width: 0;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                padding: 0;
                border: 0;
                background: transparent;
                text-align: left;
            }

            .callcenter-order-items-inline-set-head {
                cursor: pointer;
            }

            .callcenter-order-items-inline-main {
                display: flex;
                min-width: 0;
                align-items: baseline;
                gap: 4px;
                flex-wrap: wrap;
            }

            .callcenter-order-items-inline-toggle {
                display: inline-flex;
                width: 13px;
                height: 13px;
                flex: 0 0 13px;
                align-items: center;
                justify-content: center;
                border: 1px solid #cbd5e1;
                border-radius: 3px;
                color: #475569;
                font-size: 10px;
                font-weight: 700;
                line-height: 1;
            }

            .callcenter-order-items-inline-title {
                min-width: 0;
                max-width: 100%;
                overflow-wrap: anywhere;
                color: #0f172a;
                font-weight: 600;
            }

            .callcenter-order-items-inline-badge {
                flex: 0 0 auto;
                border-radius: 4px;
                background: #ffe4e6;
                color: #be123c;
                padding: 1px 3px;
                font-size: 9px;
                font-weight: 700;
                line-height: 1.15;
            }

            .callcenter-order-items-inline-volume {
                flex: 0 0 auto;
                color: #64748b;
                font-size: 10px;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-qty {
                flex: 0 0 auto;
                color: #dc2626;
                font-size: 11px;
                font-weight: 700;
                line-height: 1.2;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-preview {
                display: flex;
                min-width: 0;
                gap: 4px;
                margin-top: 1px;
                padding-left: 17px;
                color: #64748b;
                font-size: 10px;
                line-height: 1.15;
            }

            .callcenter-order-items-inline-preview > span:first-child {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-children {
                display: grid;
                gap: 2px;
                margin-top: 3px;
                padding-left: 17px;
                color: #64748b;
                font-size: 10px;
                line-height: 1.25;
            }

            .callcenter-order-items-inline-child {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-child.is-expanded {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 8px;
                white-space: normal;
            }

            .callcenter-order-items-inline-child.is-expanded span:first-child {
                min-width: 0;
                overflow-wrap: anywhere;
            }

            .callcenter-order-items-inline-child-qty {
                flex: 0 0 auto;
                color: #94a3b8;
                font-size: 9px;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-more,
            .callcenter-order-items-inline-show-more {
                color: #475569;
                font-size: 10px;
                font-weight: 600;
                white-space: nowrap;
            }

            .callcenter-order-items-inline-show-more {
                width: fit-content;
                padding: 1px 0;
                border: 0;
                background: transparent;
                cursor: pointer;
            }

            @media (max-width: 768px) {
                .callcenter-order-items-inline {
                    gap: 4px;
                    font-size: 10px;
                }

                .callcenter-order-items-inline-block {
                    padding-bottom: 4px;
                }

                .callcenter-order-items-inline-set-head,
                .callcenter-order-items-inline-regular-head {
                    gap: 6px;
                }

                .callcenter-order-items-inline-title {
                    display: -webkit-box;
                    overflow: hidden;
                    -webkit-box-orient: vertical;
                    -webkit-line-clamp: 2;
                }

                .callcenter-order-items-inline-preview,
                .callcenter-order-items-inline-children {
                    padding-left: 17px;
                }
            }
            .callcenter-mobile-order-card {
                display: none;
            }

            @media (max-width: 768px) {
                .callcenter-order-hierarchy-row {
                    display: none !important;
                }

                .fi-fo-component-ct:has(.callcenter-order-hierarchy) .callcenter-items-table {
                    display: none !important;
                }

                form:has(.callcenter-order-hierarchy) .callcenter-items-table {
                    display: none !important;
                }

                .callcenter-mobile-order-card {
                    display: block;
                    padding: 8px 10px;
                    border-top: 1px solid #fecdd3;
                    background: #ffffff;
                }

                .callcenter-mobile-order-card.is-discovery {
                    background: #fff7f8;
                }

                .callcenter-mobile-order-card-top {
                    display: grid;
                    grid-template-columns: 48px minmax(0, 1fr) 40px;
                    align-items: start;
                    gap: 8px;
                    min-width: 0;
                }

                .callcenter-mobile-order-card.is-discovery .callcenter-mobile-order-card-top {
                    grid-template-columns: minmax(0, 1fr) 40px;
                }

                .callcenter-mobile-order-card-image {
                    width: 48px;
                    height: 48px;
                    overflow: hidden;
                }

                .callcenter-mobile-order-card-image .callcenter-order-hover-image,
                .callcenter-mobile-order-card-image .callcenter-order-product-line:hover > .callcenter-order-hover-image {
                    display: inline-flex !important;
                    position: static;
                    width: 48px;
                    height: 48px;
                    transform: none;
                    pointer-events: none;
                }

                .callcenter-mobile-order-card-image img {
                    width: 44px !important;
                    height: 48px !important;
                    max-width: 44px !important;
                    max-height: 48px !important;
                    object-fit: contain;
                    border-radius: 5px;
                }

                .callcenter-mobile-order-card-info {
                    display: grid;
                    min-width: 0;
                    align-content: start;
                    gap: 2px;
                    padding-top: 1px;
                }

                .callcenter-mobile-order-card-info strong {
                    display: -webkit-box;
                    overflow: hidden;
                    -webkit-box-orient: vertical;
                    -webkit-line-clamp: 2;
                    font-size: 13px;
                    line-height: 1.2;
                }

                .callcenter-mobile-order-card-info span {
                    overflow: hidden;
                    color: #64748b;
                    font-size: 11px;
                    line-height: 1.2;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .callcenter-mobile-order-delete {
                    display: inline-flex;
                    width: 40px;
                    height: 40px;
                    align-items: center;
                    justify-content: center;
                    border: 0;
                    border-radius: 6px;
                    color: #be123c;
                    background: transparent;
                    font-size: 22px;
                    line-height: 1;
                }

                .callcenter-mobile-delete-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 100;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                    background: rgba(15, 23, 42, 0.52);
                }

                .callcenter-mobile-delete-modal-dialog {
                    position: relative;
                    width: min(100%, 505px);
                    padding: 28px 26px 26px;
                    border-radius: 14px;
                    background: #ffffff;
                    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.25);
                    text-align: center;
                }

                .callcenter-mobile-delete-modal-close {
                    position: absolute;
                    top: 12px;
                    right: 14px;
                    width: 32px;
                    height: 32px;
                    border: 0;
                    background: transparent;
                    color: #94a3b8;
                    font-size: 27px;
                    line-height: 1;
                }

                .callcenter-mobile-delete-modal-icon {
                    display: grid;
                    width: 54px;
                    height: 54px;
                    margin: 0 auto 18px;
                    place-items: center;
                    border-radius: 999px;
                    background: #fee2e2;
                    color: #ef4444;
                    font-size: 26px;
                    font-weight: 700;
                }

                .callcenter-mobile-delete-modal-dialog h2 {
                    margin: 0;
                    color: #111827;
                    font-size: 18px;
                    font-weight: 700;
                    line-height: 1.3;
                }

                .callcenter-mobile-delete-modal-dialog p {
                    margin: 8px 0 24px;
                    color: #7c8799;
                    font-size: 16px;
                    line-height: 1.35;
                }

                .callcenter-mobile-delete-modal-actions {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                }

                .callcenter-mobile-delete-modal-actions button {
                    min-height: 42px;
                    border-radius: 9px;
                    font-size: 16px;
                    font-weight: 700;
                }

                .callcenter-mobile-delete-modal-cancel {
                    border: 1px solid #d1d5db;
                    background: #ffffff;
                    color: #111827;
                }

                .callcenter-mobile-delete-modal-confirm {
                    border: 1px solid #dc2626;
                    background: #dc2626;
                    color: #ffffff;
                }

                .callcenter-mobile-order-card-bottom {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                    align-items: center;
                    gap: 6px;
                    margin-top: 7px;
                    min-width: 0;
                }

                .callcenter-mobile-order-price,
                .callcenter-mobile-order-total {
                    min-width: 0;
                    overflow: hidden;
                    font-size: 12px;
                    line-height: 1.2;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .callcenter-mobile-order-price {
                    color: #475569;
                }

                .callcenter-mobile-order-total {
                    color: #0369a1;
                    font-weight: 700;
                    text-align: right;
                }

                .callcenter-mobile-order-price s {
                    color: #94a3b8;
                    margin-right: 2px;
                }

                .callcenter-mobile-order-qty {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 2px;
                    flex: 0 0 auto;
                }

                .callcenter-mobile-order-qty button,
                .callcenter-mobile-order-qty input {
                    height: 40px;
                    min-height: 40px;
                    padding: 0;
                    border: 1px solid #cbd5e1;
                    border-radius: 5px;
                    text-align: center;
                }

                .callcenter-mobile-order-qty button {
                    width: 40px;
                    min-width: 40px;
                    color: #0f172a;
                    background: #f8fafc;
                    font-size: 18px;
                    line-height: 1;
                }

                .callcenter-mobile-order-qty input {
                    width: 34px;
                    min-width: 34px;
                    appearance: textfield;
                    -moz-appearance: textfield;
                }

                .callcenter-mobile-order-qty input::-webkit-outer-spin-button,
                .callcenter-mobile-order-qty input::-webkit-inner-spin-button {
                    margin: 0;
                    appearance: none;
                }

                .callcenter-mobile-order-discount {
                    margin-top: 3px;
                    color: #be123c;
                    font-size: 11px;
                    line-height: 1.2;
                }

                .callcenter-mobile-order-card .callcenter-discovery-details {
                    margin: 7px -10px -8px;
                }

                .callcenter-mobile-order-card + .callcenter-discovery-details {
                    display: block;
                }

                .callcenter-discovery-details {
                    border-top: 1px solid #fecdd3;
                }

                .callcenter-discovery-summary {
                    min-height: 40px;
                    padding: 4px 10px;
                }

                .callcenter-discovery-child {
                    grid-template-columns: 24px minmax(0, 1fr) 42px 42px;
                    gap: 6px;
                    min-height: 56px;
                    padding: 6px 10px;
                }

                .callcenter-discovery-child-name,
                .callcenter-discovery-edit-current {
                    min-width: 0;
                }

                .callcenter-discovery-child-name .callcenter-order-product-line,
                .callcenter-discovery-edit-current .callcenter-order-product-line {
                    min-width: 0;
                    align-items: center;
                }

                .callcenter-discovery-child-name .callcenter-order-hover-image,
                .callcenter-discovery-edit-current .callcenter-order-hover-image {
                    display: inline-flex !important;
                    position: static;
                    width: 36px;
                    height: 44px;
                    flex: 0 0 36px;
                    transform: none;
                }

                .callcenter-discovery-child-name .callcenter-order-hover-image img,
                .callcenter-discovery-edit-current .callcenter-order-hover-image img {
                    width: 34px !important;
                    height: 44px !important;
                    max-width: 34px !important;
                    max-height: 44px !important;
                }

                .callcenter-discovery-child-name strong,
                .callcenter-discovery-edit-current strong {
                    display: -webkit-box;
                    overflow: hidden;
                    -webkit-box-orient: vertical;
                    -webkit-line-clamp: 2;
                    line-height: 1.2;
                }

                .callcenter-discovery-child-volume,
                .callcenter-discovery-child-need {
                    white-space: nowrap;
                }

                .callcenter-discovery-edit-row {
                    grid-template-columns: 24px minmax(0, 1fr) 76px;
                    gap: 6px;
                    min-height: 56px;
                    padding: 6px 10px;
                }

                .callcenter-discovery-replace-btn,
                .callcenter-discovery-summary button,
                .callcenter-discovery-edit-actions button {
                    min-height: 40px;
                }

                .callcenter-discovery-edit-actions {
                    flex-wrap: wrap;
                    gap: 6px;
                    padding: 8px 10px;
                }
            }
        </style>
@endif

@if($isCallcenterOrderForm)
    <style>
        [x-cloak] {
            display: none !important;
        }

        .fi-resource-edit-record-page .fi-fo,
        .fi-resource-create-record-page .fi-fo {
            column-gap: 0.5rem !important;
            row-gap: 0.75rem !important;
        }

        .fi-resource-edit-record-page .fi-section-content-ctn.p-6,
        .fi-resource-create-record-page .fi-section-content-ctn.p-6,
        .fi-resource-edit-record-page .fi-section-content.p-6,
        .fi-resource-create-record-page .fi-section-content.p-6 {
            padding: 0.5rem !important;
        }

        .fi-resource-edit-record-page .fi-fo-label,
        .fi-resource-create-record-page .fi-fo-label,
        .fi-resource-edit-record-page .fi-fo-field-wrp-label,
        .fi-resource-create-record-page .fi-fo-field-wrp-label,
        .fi-resource-edit-record-page .fi-fo-field-wrp-label span,
        .fi-resource-create-record-page .fi-fo-field-wrp-label span,
        .fi-resource-edit-record-page label,
        .fi-resource-create-record-page label,
        .fi-resource-edit-record-page .fi-section-header-heading,
        .fi-resource-create-record-page .fi-section-header-heading,
        .fi-resource-edit-record-page .fi-tabs-item-label,
        .fi-resource-create-record-page .fi-tabs-item-label,
        .fi-resource-edit-record-page .fi-ta-header-cell-label,
        .fi-resource-create-record-page .fi-ta-header-cell-label,
        .callcenter-items-table .table-repeater-header-column,
        .callcenter-items-table .table-repeater-header-column * {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .fi-resource-edit-record-page .fi-input,
        .fi-resource-create-record-page .fi-input,
        .fi-resource-edit-record-page input,
        .fi-resource-create-record-page input,
        .fi-resource-edit-record-page .fi-select-input,
        .fi-resource-create-record-page .fi-select-input,
        .fi-resource-edit-record-page .choices__inner,
        .fi-resource-create-record-page .choices__inner,
        .fi-resource-edit-record-page .choices__item,
        .fi-resource-create-record-page .choices__item,
        .fi-resource-edit-record-page .ts-control,
        .fi-resource-create-record-page .ts-control,
        .fi-resource-edit-record-page .ts-control .item,
        .fi-resource-create-record-page .ts-control .item,
        .fi-resource-edit-record-page .flatpickr-input,
        .fi-resource-create-record-page .flatpickr-input {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .callcenter-items-table .table-repeater-header-column,
        .callcenter-items-table .table-repeater-column {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .table-repeater-container thead .table-repeater-header-column {
            background: #e0f2fe !important;
        }

        .callcenter-items-table .table-repeater-container tbody tr {
            border-bottom: 1px solid #dbeafe;
        }

        .callcenter-items-table .table-repeater-container tbody tr + tr {
            box-shadow: inset 0 1px 0 #e0f2fe;
        }

        .callcenter-items-table .table-repeater-container table {
            border-collapse: separate !important;
            border-spacing: 4px 0 !important;
            font-size: 12px !important;
            line-height: 1.25rem;
        }

        .callcenter-items-table--nova-post .table-repeater-container {
            overflow: visible !important;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
        }

        .callcenter-items-table--nova-post .table-repeater-container table {
            border-spacing: 0 !important;
        }

        .callcenter-items-table--nova-post .table-repeater-container thead tr {
            background: #e0f2fe;
        }

        .callcenter-items-table--nova-post .table-repeater-container tbody tr {
            min-height: 58px;
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            border-bottom: 0;
            box-shadow: none;
        }

        .callcenter-items-table--nova-post .table-repeater-container th,
        .callcenter-items-table--nova-post .table-repeater-container td {
            padding: 9px 10px !important;
            vertical-align: middle !important;
        }

        .callcenter-items-table--nova-post .table-repeater-container input,
        .callcenter-items-table--nova-post .table-repeater-container .choices__inner,
        .callcenter-items-table--nova-post .table-repeater-container .ts-control {
            min-height: 30px;
            border-color: #d1d5db;
            border-radius: 6px;
            background: #ffffff;
        }

        .callcenter-items-table--nova-post .callcenter-product-card-title,
        .callcenter-items-table--nova-post .callcenter-inline-select-display {
            color: #111827;
            font-weight: 700;
        }

        .callcenter-items-table--compact .callcenter-product-card-cell,
        .callcenter-items-table--compact .table-repeater-container table th:nth-child(2),
        .callcenter-items-table--compact .table-repeater-container table td:nth-child(2) {
            display: none !important;
        }

        .callcenter-items-table--photo .table-repeater-container table {
            border-spacing: 6px 6px !important;
        }

        .callcenter-items-table--photo .table-repeater-container tbody tr {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .callcenter-items-table--photo .table-repeater-container table td {
            vertical-align: middle !important;
            padding-top: 6px !important;
            padding-bottom: 6px !important;
        }

        .callcenter-items-table--photo .table-repeater-container table th:nth-child(3),
        .callcenter-items-table--photo .table-repeater-container table td:nth-child(3),
        .callcenter-items-table--photo .table-repeater-container table th:nth-child(4),
        .callcenter-items-table--photo .table-repeater-container table td:nth-child(4) {
            display: none !important;
        }

        .callcenter-items-table--photo .callcenter-inline-select,
        .callcenter-items-table--photo .callcenter-inline-select-display,
        .callcenter-items-table--photo .callcenter-unit-text {
            display: none !important;
        }

        .callcenter-items-table--photo .table-repeater-column:has(.callcenter-inline-select),
        .callcenter-items-table--photo .table-repeater-column:has(.callcenter-unit-text) {
            display: none !important;
        }

        .callcenter-product-card {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 220px;
        }

        .callcenter-items-table--photo .callcenter-product-card {
            gap: 8px;
            min-width: 0;
        }

        .callcenter-product-card-image {
            width: 58px;
            height: 48px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            object-fit: cover;
            flex: 0 0 auto;
        }

        .callcenter-items-table--photo .callcenter-product-card-image {
            width: 48px;
            height: 40px;
        }

        .callcenter-product-card-image.is-empty {
            display: block;
        }

        .callcenter-product-card-info {
            min-width: 0;
        }

        .callcenter-product-card-title {
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .callcenter-product-card-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 8px;
            margin-top: 4px;
            color: #64748b;
            font-size: 11px;
            line-height: 1.2;
        }

        .callcenter-product-card-article {
            color: #c2410c;
            font-weight: 600;
        }

        .callcenter-mobile-order-card-info .callcenter-product-card-article {
            color: #c2410c;
            font-weight: 600;
        }

        .callcenter-product-card-char {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
        }

        .callcenter-product-card-char-icon {
            width: 14px;
            height: 14px;
            object-fit: contain;
            opacity: 0.65;
        }

        .callcenter-items-table .table-repeater-container table th,
        .callcenter-items-table .table-repeater-container table td,
        .callcenter-items-table .callcenter-inline-display,
        .callcenter-items-table .callcenter-inline-input-display,
        .callcenter-items-table .callcenter-inline-input-price,
        .callcenter-items-table .callcenter-inline-input-qty,
        .callcenter-items-table .callcenter-inline-select .choices__inner,
        .callcenter-items-table .callcenter-inline-select .choices__item,
        .callcenter-items-table .callcenter-unit-text,
        .callcenter-items-table .callcenter-inline-item-total {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .callcenter-inline-hidden {
            display: none !important;
        }

        .callcenter-items-table .callcenter-inline-display {
            display: block;
            min-height: 28px;
            line-height: 28px;
            padding: 0 6px;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: text;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .callcenter-items-table .callcenter-inline-display:hover {
            background: #f8fafc;
        }

        .callcenter-items-table .callcenter-inline-input-display {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 400;
            padding-right: 0;
            padding-left: 0;
        }

        .callcenter-items-table .callcenter-inline-input-display.is-qty-display {
            text-align: center;
        }

        .callcenter-items-table .callcenter-inline-input-display:hover {
            background: transparent;
        }

        .callcenter-items-table .callcenter-inline-display.is-empty {
            color: #94a3b8;
        }

        .callcenter-order-hierarchy {
            overflow: visible;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #ffffff;
        }

        .callcenter-order-hierarchy--mobile-only {
            display: none;
        }

        .callcenter-order-add-actions--mobile-only {
            display: none;
        }

        .fi-fo-component-ct:has(.callcenter-order-hierarchy--mobile-only) {
            display: none !important;
        }

        .callcenter-order-hierarchy-head,
        .callcenter-order-hierarchy-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 7% 11% 13% 14% 12% 4%;
            align-items: center;
            gap: 6px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .callcenter-order-hierarchy-head > div,
        .callcenter-order-hierarchy-row > div {
            min-width: 0;
            max-width: 100%;
        }

        .callcenter-order-hierarchy-head {
            padding: 10px 12px;
            background: #e0f2fe;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .callcenter-order-hierarchy-head > div:nth-child(2),
        .callcenter-order-hierarchy-head > div:nth-child(3) {
            text-align: center;
        }

        .callcenter-order-hierarchy-head > div:nth-child(4),
        .callcenter-order-hierarchy-head > div:nth-child(5),
        .callcenter-order-hierarchy-head > div:nth-child(6) {
            padding-right: 6px;
            text-align: right;
        }

        .callcenter-order-hierarchy-row {
            min-height: 58px;
            padding: 9px 10px;
            border-top: 1px solid #e5e7eb;
        }

        .callcenter-order-hierarchy-row.is-parent {
            background: #fff7f8;
            border-top-color: #fecdd3;
        }

        .callcenter-order-hierarchy-row.is-regular {
            background: #ffffff;
        }

        .callcenter-order-title {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .callcenter-order-product-line {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 0;
            gap: 4px;
            cursor: zoom-in;
        }

        .callcenter-order-hover-image {
            display: none;
            position: absolute;
            left: 28px;
            top: calc(100% + 8px);
            z-index: 30;
            pointer-events: none;
        }

        .callcenter-order-product-line:hover > .callcenter-order-hover-image {
            display: block !important;
        }

        .callcenter-order-hover-image > img {
            display: block;
            width: 180px;
            height: 180px;
            max-width: none !important;
            max-height: none !important;
            min-width: 180px;
            min-height: 180px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f8fafc;
            object-fit: contain;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.2);
        }

        .callcenter-order-title strong {
            color: #111827;
            font-size: 12px;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .callcenter-order-title span {
            color: #64748b;
            font-size: 11px;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .callcenter-discovery-discount {
            color: #be123c !important;
            font-weight: 700;
        }

        .callcenter-order-qty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0;
        }

        .callcenter-order-qty button,
        .callcenter-order-actions button {
            width: 20px;
            height: 26px;
            box-sizing: border-box;
            max-width: 100%;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #334155;
            font-size: 13px;
            line-height: 1;
            cursor: pointer;
        }

        .callcenter-order-actions button {
            border-color: #fecdd3;
            color: #be123c;
        }

        .callcenter-order-qty input,
        .callcenter-order-price input {
            width: 34px;
            min-height: 26px;
            border: 1px solid #d1d5db;
            border-left: 0;
            border-right: 0;
            text-align: center;
            font-size: 11px;
            color: #111827;
            background: #ffffff;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .callcenter-order-qty input::-webkit-outer-spin-button,
        .callcenter-order-qty input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .callcenter-order-qty:not(:has(button)) input {
            width: 42px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }

        .callcenter-order-price {
            display: grid;
            justify-items: end;
            gap: 2px;
            color: #111827;
            font-size: 11px;
            font-variant-numeric: tabular-nums;
        }

        .callcenter-order-price input,
        .callcenter-order-total input,
        .callcenter-order-discount input {
            width: 52px;
            max-width: 100%;
            border: 1px solid #d1d5db;
            text-align: right;
            padding-right: 6px;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .callcenter-order-price input::-webkit-inner-spin-button,
        .callcenter-order-price input::-webkit-outer-spin-button,
        .callcenter-order-total input::-webkit-inner-spin-button,
        .callcenter-order-total input::-webkit-outer-spin-button,
        .callcenter-order-discount input::-webkit-inner-spin-button,
        .callcenter-order-discount input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .callcenter-order-price .is-old {
            color: #94a3b8;
            text-decoration: line-through;
        }

        .callcenter-order-total {
            text-align: right;
            color: #0369a1;
            font-size: 12px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .callcenter-order-discount {
            display: grid;
            justify-items: end;
            color: #be123c;
            font-size: 11px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .callcenter-order-discount input {
            width: 68px;
            min-width: 0;
            max-width: 100%;
            border: 1px solid #d1d5db;
            text-align: right;
            padding-right: 6px;
            color: #be123c;
            background: #ffffff;
        }

        .callcenter-order-actions {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .callcenter-discovery-details {
            border-top: 1px solid #fecdd3;
            background: #fff7f8;
        }

        .callcenter-discovery-summary {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 12px;
        }

        .callcenter-discovery-toggle-icon {
            width: 8px;
            height: 8px;
            flex: 0 0 8px;
            border-right: 2px solid #9f1239;
            border-bottom: 2px solid #9f1239;
            transform: rotate(-45deg);
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .callcenter-discovery-toggle-icon.is-open {
            transform: rotate(45deg);
        }

        .callcenter-discovery-summary button {
            border: 0;
            background: transparent;
            color: #9f1239;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .callcenter-discovery-children {
            display: grid;
            gap: 1px;
            background: #fecdd3;
        }

        .callcenter-discovery-child {
            display: grid;
            grid-template-columns: 24px minmax(0, 1fr) 56px 120px;
            align-items: center;
            gap: 6px;
            padding: 8px 10px;
            background: #ffffff;
        }

        .callcenter-discovery-child-index {
            color: #9f1239;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        .callcenter-discovery-child-name {
            display: grid;
            min-width: 0;
        }

        .callcenter-discovery-child-name strong {
            color: #374151;
            font-size: 12px;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .callcenter-discovery-child-name span,
        .callcenter-discovery-child-volume,
        .callcenter-discovery-child-need {
            color: #64748b;
            font-size: 11px;
            line-height: 1.2;
        }

        .callcenter-discovery-child-need {
            text-align: right;
        }

        .callcenter-discovery-edit {
            display: grid;
            gap: 1px;
            background: #fecdd3;
        }

        .callcenter-discovery-edit-row {
            display: grid;
            grid-template-columns: 24px minmax(0, 1fr) 88px;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: #ffffff;
        }

        .callcenter-discovery-edit-current {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .callcenter-discovery-edit-current strong {
            color: #374151;
            font-size: 12px;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .callcenter-discovery-edit-current span {
            color: #64748b;
            font-size: 11px;
            line-height: 1.2;
        }

        .callcenter-discovery-replace-btn {
            min-height: 30px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            padding: 0 9px;
            cursor: pointer;
        }

        .callcenter-discovery-edit-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px;
            background: #fff7f8;
        }

        .callcenter-discovery-edit-actions button {
            min-height: 30px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 0 10px;
            cursor: pointer;
        }

        .callcenter-discovery-edit-actions button.is-primary {
            border-color: #9f1239;
            background: #9f1239;
            color: #ffffff;
        }

        @media (max-width: 1500px) {
            .callcenter-order-hierarchy-head {
                display: none;
            }

            .callcenter-order-hierarchy-row {
                grid-template-columns: minmax(0, 1fr) 6% 11% 13% 12% 12% 4%;
                align-items: center;
                gap: 6px 8px;
            }

            .callcenter-order-hierarchy-row > div:nth-child(2) {
                font-size: 11px;
                color: #64748b;
            }

            .callcenter-order-hierarchy-row > div:nth-child(3) {
                justify-content: flex-start;
            }

            .callcenter-order-total,
            .callcenter-order-price,
            .callcenter-order-discount {
                justify-items: end;
                text-align: right;
            }

            .callcenter-order-actions {
                justify-content: flex-end;
            }

            .callcenter-discovery-child {
                grid-template-columns: 24px minmax(0, 1fr) 92px;
            }

            .callcenter-discovery-child-volume {
                grid-column: 2;
            }

            .callcenter-discovery-child-need {
                text-align: right;
            }

            .callcenter-discovery-edit-row {
                grid-template-columns: 24px minmax(0, 1fr);
            }

            .callcenter-discovery-replace-btn {
                grid-column: 2 / -1;
                width: fit-content;
            }

        }

        @media (max-width: 640px) {
            .callcenter-order-hierarchy-row {
                grid-template-columns: minmax(0, 1fr) minmax(72px, auto);
                grid-template-areas:
                    "title title"
                    "size qty"
                    "price total"
                    "discount actions";
                gap: 6px 10px;
                min-height: 0;
                padding: 10px;
            }

            .callcenter-order-hierarchy-row > div:nth-child(1) {
                grid-area: title;
            }

            .callcenter-order-hierarchy-row > div:nth-child(2) {
                grid-area: size;
                justify-self: start;
            }

            .callcenter-order-hierarchy-row > div:nth-child(3) {
                grid-area: qty;
                justify-self: end;
            }

            .callcenter-order-hierarchy-row > div:nth-child(4) {
                grid-area: price;
                justify-self: start;
            }

            .callcenter-order-hierarchy-row > div:nth-child(5) {
                grid-area: total;
                justify-self: end;
            }

            .callcenter-order-hierarchy-row > div:nth-child(6) {
                grid-area: discount;
                justify-self: start;
            }

            .callcenter-order-hierarchy-row > div:nth-child(7) {
                grid-area: actions;
                justify-self: end;
            }

            .callcenter-order-qty {
                justify-content: flex-end;
            }

            .callcenter-order-price,
            .callcenter-order-total,
            .callcenter-order-discount {
                text-align: left;
                justify-items: start;
            }

            .callcenter-order-total,
            .callcenter-order-discount {
                justify-self: end;
                text-align: right;
                justify-items: end;
            }

            .callcenter-order-price input,
            .callcenter-order-total input {
                width: 64px;
            }

            .callcenter-order-discount input {
                width: 46px;
            }

            .callcenter-order-title strong {
                font-size: 13px;
            }

            .callcenter-items-table .table-repeater-container {
                overflow: hidden !important;
            }

            .callcenter-items-table .table-repeater-container table {
                table-layout: fixed;
                width: 100% !important;
                border-spacing: 0 !important;
            }

            .callcenter-items-table .table-repeater-container th,
            .callcenter-items-table .table-repeater-container td {
                min-width: 0 !important;
                padding: 5px 3px !important;
                overflow: hidden;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(3),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(3) {
                width: 38% !important;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(4),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(4) {
                width: 8% !important;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(5),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(5) {
                width: 12% !important;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(6),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(6) {
                width: 14% !important;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(7),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(7) {
                width: 14% !important;
            }

            .callcenter-items-table--compact .table-repeater-container table th:nth-child(8),
            .callcenter-items-table--compact .table-repeater-container table td:nth-child(8) {
                width: 10% !important;
            }

            .callcenter-items-table--photo .table-repeater-container table th:nth-child(2),
            .callcenter-items-table--photo .table-repeater-container table td:nth-child(2) {
                width: 38% !important;
            }

            .callcenter-items-table--photo .table-repeater-container table th:nth-child(5),
            .callcenter-items-table--photo .table-repeater-container table td:nth-child(5) {
                width: 12% !important;
            }

            .callcenter-items-table--photo .table-repeater-container table th:nth-child(6),
            .callcenter-items-table--photo .table-repeater-container table td:nth-child(6) {
                width: 14% !important;
            }

            .callcenter-items-table--photo .table-repeater-container table th:nth-child(7),
            .callcenter-items-table--photo .table-repeater-container table td:nth-child(7) {
                width: 14% !important;
            }

            .callcenter-items-table--photo .table-repeater-container table th:nth-child(8),
            .callcenter-items-table--photo .table-repeater-container table td:nth-child(8) {
                width: 10% !important;
            }

            .callcenter-items-table .fi-input,
            .callcenter-items-table input,
            .callcenter-items-table .choices,
            .callcenter-items-table .ts-wrapper {
                max-width: 100% !important;
            }

            .callcenter-items-table--compact .table-repeater-container thead,
            .callcenter-items-table--photo .table-repeater-container thead {
                display: none !important;
            }

            .callcenter-items-table--compact .table-repeater-container table,
            .callcenter-items-table--compact .table-repeater-container tbody,
            .callcenter-items-table--photo .table-repeater-container table,
            .callcenter-items-table--photo .table-repeater-container tbody {
                display: block;
            }

            .callcenter-items-table--compact .table-repeater-container tbody tr,
            .callcenter-items-table--photo .table-repeater-container tbody tr {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 58px;
                gap: 6px 10px;
                min-height: 0;
                margin: 6px;
                padding: 10px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #ffffff;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td,
            .callcenter-items-table--photo .table-repeater-container tbody td {
                display: block !important;
                width: auto !important;
                padding: 0 !important;
                border: 0 !important;
                overflow: visible;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(1),
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(2) {
                display: none !important;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(3) { grid-area: product; }
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(4) { grid-area: unit; }
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(5) { grid-area: qty; }
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(6) { grid-area: price; }
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(7) { grid-area: total; }
            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(8) { grid-area: discount; }

            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(1),
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(3),
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(4) {
                display: none !important;
            }

            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(2) { grid-area: product; }
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(5) { grid-area: qty; }
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(6) { grid-area: price; }
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(7) { grid-area: total; }
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(8) { grid-area: discount; }

            .callcenter-items-table--compact .table-repeater-container tbody td:last-child,
            .callcenter-items-table--photo .table-repeater-container tbody td:last-child {
                grid-area: actions;
                align-self: start;
                justify-self: end;
            }

            .callcenter-items-table--compact .table-repeater-container tbody tr,
            .callcenter-items-table--photo .table-repeater-container tbody tr {
                grid-template-areas:
                    "product actions"
                    "unit qty"
                    "price total"
                    "discount discount";
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(4),
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(5) {
                justify-self: start;
                color: #64748b;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(7),
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(7) {
                justify-self: end;
                color: #0369a1;
                font-weight: 700;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:nth-child(8),
            .callcenter-items-table--photo .table-repeater-container tbody td:nth-child(8) {
                justify-self: start;
                color: #be123c;
            }

            .callcenter-items-table--compact .table-repeater-container tbody td:last-child button,
            .callcenter-items-table--photo .table-repeater-container tbody td:last-child button {
                min-width: 40px;
                min-height: 40px;
            }
        }

        @media (max-width: 768px) {
            .callcenter-order-hierarchy--mobile-only {
                display: block;
            }

            .callcenter-order-add-actions--mobile-only {
                display: block;
            }

            .fi-fo-component-ct:has(.callcenter-order-hierarchy--mobile-only) {
                display: block !important;
            }

            .callcenter-order-hierarchy {
                overflow: visible;
                border-radius: 10px;
            }

            .callcenter-order-hierarchy-row {
                grid-template-columns: minmax(0, 1fr) 58px;
                grid-template-areas:
                    "title actions"
                    "size qty"
                    "price total"
                    "discount discount";
                gap: 6px 10px;
                min-height: 0;
                padding: 10px;
                border-top-color: #fecdd3;
            }

            .callcenter-order-hierarchy-row > div:nth-child(1) { grid-area: title; }
            .callcenter-order-hierarchy-row > div:nth-child(2) { grid-area: size; justify-self: start; }
            .callcenter-order-hierarchy-row > div:nth-child(3) { grid-area: qty; justify-self: end; }
            .callcenter-order-hierarchy-row > div:nth-child(4) { grid-area: price; justify-self: start; }
            .callcenter-order-hierarchy-row > div:nth-child(5) { grid-area: total; justify-self: end; }
            .callcenter-order-hierarchy-row > div:nth-child(6) { grid-area: discount; justify-self: start; }
            .callcenter-order-hierarchy-row > div:nth-child(7) { grid-area: actions; justify-self: end; }

            .callcenter-order-title,
            .callcenter-order-product-line {
                min-width: 0;
            }

            .callcenter-order-product-line {
                align-items: flex-start;
            }

            .callcenter-order-product-line strong {
                display: -webkit-box;
                overflow: hidden;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                line-height: 1.25;
            }

            .callcenter-order-hover-image,
            .callcenter-order-product-line:hover > .callcenter-order-hover-image {
                position: static;
                display: inline-flex !important;
                flex: 0 0 48px;
                width: 48px;
                height: 56px;
                pointer-events: none;
                transform: none;
            }

            .callcenter-order-hover-image > img {
                width: 44px;
                height: 56px;
                min-width: 44px;
                min-height: 56px;
                max-width: 44px !important;
                max-height: 56px !important;
                border-radius: 5px;
            }

            .callcenter-order-price,
            .callcenter-order-total,
            .callcenter-order-discount {
                min-width: 0;
                font-size: 11px;
            }

            .callcenter-order-price::before { content: 'Ціна: '; color: #64748b; }
            .callcenter-order-total::before { content: 'Разом: '; color: #64748b; }
            .callcenter-order-price::after,
            .callcenter-order-total::after,
            .callcenter-order-discount:not(.is-empty):not(.is-set-discount)::after { content: ' грн'; color: #64748b; }

            .callcenter-order-discount.is-empty {
                display: none;
            }

            .callcenter-order-discount {
                justify-self: start;
                display: flex;
                align-items: center;
                gap: 4px;
                text-align: left;
            }

            .callcenter-order-discount input,
            .callcenter-order-price input,
            .callcenter-order-total input {
                width: 64px;
                max-width: 64px;
            }

            .callcenter-order-qty button,
            .callcenter-order-actions button,
            .callcenter-discovery-replace-btn,
            .callcenter-discovery-summary button {
                min-width: 40px;
                min-height: 40px;
            }

            .callcenter-order-qty input {
                width: 36px;
                min-height: 40px;
            }

            .callcenter-discovery-summary {
                min-height: 44px;
                gap: 8px;
                padding: 6px 10px;
            }

            .callcenter-discovery-summary button {
                min-height: 36px;
                padding: 0 8px;
            }

            .callcenter-discovery-child {
                grid-template-columns: 24px minmax(0, 1fr) 42px 42px;
                gap: 6px;
                padding: 8px 10px;
            }

            .callcenter-discovery-child-need {
                white-space: nowrap;
            }

            .callcenter-discovery-edit-row {
                grid-template-columns: 24px minmax(0, 1fr) 76px;
                gap: 6px;
                padding: 8px 10px;
            }

            .callcenter-discovery-replace-btn {
                min-width: 76px;
                padding: 0 8px;
            }

            .callcenter-discovery-edit-actions {
                flex-wrap: wrap;
            }

            .callcenter-discovery-edit-actions button {
                min-height: 40px;
            }
        }

        .callcenter-discovery-child-marker {
            display: none !important;
        }

        .callcenter-items-table .table-repeater-row:has(.callcenter-discovery-child-marker) {
            display: none !important;
        }

        .callcenter-items-table .callcenter-inline-select-display,
        .callcenter-items-table .callcenter-inline-select .choices__inner,
        .callcenter-items-table .callcenter-inline-select .choices__item {
            font-size: 12px !important;
        }

        .callcenter-kitchen-note-btn {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            border: 1px solid #16a34a;
            color: #15803d;
            background: #f0fdf4;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .callcenter-kitchen-note-btn.is-active {
            background: #22c55e;
            color: #ffffff;
            border-color: #16a34a;
        }

        .callcenter-kitchen-note-popover {
            position: absolute;
            top: -4px;
            left: calc(100% + 8px);
            z-index: 80;
            width: 260px;
            background: #ffffff;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.18);
            padding: 8px;
        }

        .callcenter-kitchen-note-textarea {
            width: 100%;
            min-height: 84px;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.35;
            resize: vertical;
            outline: none;
        }

        .callcenter-kitchen-note-textarea:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
        }

        .callcenter-kitchen-note-actions {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .callcenter-kitchen-note-save,
        .callcenter-kitchen-note-cancel {
            border-radius: 8px;
            font-size: 12px;
            line-height: 1;
            padding: 6px 10px;
            cursor: pointer;
        }

        .callcenter-kitchen-note-save {
            border: 1px solid #16a34a;
            background: #16a34a;
            color: #ffffff;
        }

        .callcenter-kitchen-note-cancel {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #334155;
        }

        .callcenter-items-table .callcenter-inline-select-display.has-note {
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
            line-height: 1.2;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .callcenter-items-table .callcenter-inline-select-display-note {
            display: block;
            margin-top: 4px;
            margin-left: 8px;
            margin-right: 4px;
            color: #15803d;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 700;
            background: #dcfce7;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 3px 6px;
            white-space: normal;
            word-break: break-word;
        }

        .callcenter-items-table .callcenter-unit-text {
            font-size: 10px !important;
            line-height: 1.25rem;
            background: #fef08a;
            border-radius: 6px;
            padding: 0 4px;
        }

        .callcenter-items-table .callcenter-unit-with-tooltip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: help;
        }

        .callcenter-items-table .callcenter-unit-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
        }

        .callcenter-items-table .callcenter-unit-tooltip {
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 90;
            width: 260px;
            max-width: min(260px, 45vw);
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #bfdbfe;
            background: #ffffff;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.35;
            text-align: left;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.16);
            white-space: normal;
            word-break: break-word;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.12s ease;
        }

        .callcenter-items-table .callcenter-unit-with-tooltip:hover .callcenter-unit-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .callcenter-items-table .callcenter-inline-item-total {
            color: #0369a1 !important;
            font-size: 12px !important;
            line-height: 1.25rem !important;
            font-weight: 700 !important;
            font-variant-numeric: tabular-nums;
        }

        .callcenter-items-table .callcenter-inline-item-total,
        .callcenter-items-table .callcenter-inline-item-total * {
            color: #0369a1 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
        }

        .callcenter-items-table .callcenter-inline-input-price {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-input-discount {
            min-height: 28px !important;
            text-align: right !important;
            color: #c2410c !important;
            font-variant-numeric: tabular-nums;
        }

        .callcenter-items-table .callcenter-inline-editable-wrapper .fi-input-wrp {
            display: none !important;
        }

        .callcenter-items-table--photo .callcenter-inline-qty-wrapper .fi-input-wrp {
            display: flex !important;
            align-items: center !important;
            min-height: 32px !important;
            box-shadow: none !important;
            border: 1px solid #d1d5db !important;
            background: #ffffff !important;
        }

        .callcenter-items-table--photo .callcenter-inline-qty-wrapper .callcenter-inline-hidden {
            display: flex !important;
        }

        .callcenter-items-table--photo .callcenter-inline-qty-wrapper .callcenter-inline-input-display {
            display: none !important;
        }

        .callcenter-items-table--photo .callcenter-inline-input-qty {
            min-height: 30px !important;
            text-align: center !important;
        }

        .callcenter-items-table .callcenter-inline-editable-wrapper.is-editing .fi-input-wrp {
            display: flex !important;
            align-items: center !important;
            min-height: 28px !important;
            padding-left: 2px !important;
            padding-right: 2px !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
            background: #ffffff !important;
        }

        .callcenter-items-table .callcenter-inline-price-wrapper.is-editing .fi-input-wrp-input {
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-price-wrapper.is-editing .callcenter-inline-input-price {
            width: 100% !important;
            min-width: 0 !important;
            text-align: right !important;
            font-variant-numeric: tabular-nums;
            line-height: 1.1 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-input-display.is-discount-display {
            color: #c2410c;
            font-weight: 700;
        }

        .callcenter-items-table .callcenter-inline-discount-wrapper.is-editing .fi-input-wrp-input {
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-discount-wrapper.is-editing .callcenter-inline-input-discount {
            width: 100% !important;
            min-width: 0 !important;
            line-height: 1.1 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-input-qty {
            min-height: 28px !important;
            text-align: center !important;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .callcenter-items-table .callcenter-inline-input-qty::-webkit-outer-spin-button,
        .callcenter-items-table .callcenter-inline-input-qty::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .callcenter-items-table .callcenter-inline-select .ts-control {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-select .choices {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-select .choices[data-type*="select-one"]::after {
            display: none !important;
        }

        /* Hide clear/remove crosses in all selects on callcenter order form */
        .fi-resource-edit-record-page .choices__button,
        .fi-resource-create-record-page .choices__button,
        .fi-resource-edit-record-page .ts-wrapper .clear-button,
        .fi-resource-create-record-page .ts-wrapper .clear-button,
        .fi-resource-edit-record-page .ts-control .remove,
        .fi-resource-create-record-page .ts-control .remove {
            display: none !important;
        }

        .callcenter-client-select {
            position: relative;
        }

        .callcenter-client-side-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 50;
            max-height: 280px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            display: none;
        }

        .callcenter-client-side-item {
            padding: 8px 10px;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
            font-size: 13px;
        }

        .callcenter-client-side-item:last-child {
            border-bottom: none;
        }

        .callcenter-client-side-item:hover {
            background: #f8fafc;
        }

        .callcenter-client-side-item-phone {
            font-weight: 600;
            margin-right: 6px;
        }

        .callcenter-client-side-item-name {
            color: #64748b;
        }

        .callcenter-mobile-order-card {
            display: none;
        }

        @media (max-width: 768px) {
            .callcenter-order-hierarchy-row {
                display: none !important;
            }

            form:has(.callcenter-order-hierarchy) .callcenter-items-table,
            .fi-fo-component-ct:has(.callcenter-order-hierarchy) .callcenter-items-table {
                display: none !important;
            }

            .callcenter-mobile-order-card {
                display: block;
                padding: 8px 10px;
                border-top: 1px solid #fecdd3;
                background: #fff;
            }

            .callcenter-mobile-order-card.is-discovery {
                background: #fff7f8;
            }

            .callcenter-mobile-order-card-top {
                display: grid;
                grid-template-columns: 48px minmax(0, 1fr) 40px;
                gap: 8px;
                align-items: start;
            }

            .callcenter-mobile-order-card.is-discovery .callcenter-mobile-order-card-top {
                grid-template-columns: minmax(0, 1fr) 40px;
            }

            .callcenter-mobile-order-card-image,
            .callcenter-mobile-order-card-image .callcenter-order-hover-image {
                width: 48px;
                height: 48px;
            }

            .callcenter-mobile-order-card-image .callcenter-order-hover-image {
                display: inline-flex !important;
                position: static;
                transform: none;
                pointer-events: none;
            }

            .callcenter-mobile-order-card-image img {
                width: 44px !important;
                height: 48px !important;
                max-width: 44px !important;
                max-height: 48px !important;
                object-fit: contain;
            }

            .callcenter-mobile-order-card-info {
                display: grid;
                min-width: 0;
                gap: 2px;
            }

            .callcenter-mobile-order-card-info strong {
                display: -webkit-box;
                overflow: hidden;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                font-size: 13px;
                line-height: 1.2;
            }

            .callcenter-mobile-order-card-info span {
                overflow: hidden;
                color: #64748b;
                font-size: 11px;
                line-height: 1.2;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .callcenter-mobile-order-delete {
                display: inline-flex;
                width: 40px;
                height: 40px;
                align-items: center;
                justify-content: center;
                border: 0;
                background: transparent;
                color: #be123c;
                font-size: 20px;
            }

            .callcenter-mobile-delete-modal {
                position: fixed;
                inset: 0;
                z-index: 100;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
                background: rgba(15, 23, 42, 0.52);
            }

            .callcenter-mobile-delete-modal-dialog {
                position: relative;
                width: min(100%, 505px);
                padding: 28px 26px 26px;
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.25);
                text-align: center;
            }

            .callcenter-mobile-delete-modal-close {
                position: absolute;
                top: 12px;
                right: 14px;
                width: 32px;
                height: 32px;
                border: 0;
                background: transparent;
                color: #94a3b8;
                font-size: 27px;
                line-height: 1;
            }

            .callcenter-mobile-delete-modal-icon {
                display: grid;
                width: 54px;
                height: 54px;
                margin: 0 auto 18px;
                place-items: center;
                border-radius: 999px;
                background: #fee2e2;
                color: #ef4444;
                font-size: 26px;
                font-weight: 700;
            }

            .callcenter-mobile-delete-modal-dialog h2 {
                margin: 0;
                color: #111827;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.3;
            }

            .callcenter-mobile-delete-modal-dialog p {
                margin: 8px 0 24px;
                color: #7c8799;
                font-size: 16px;
                line-height: 1.35;
            }

            .callcenter-mobile-delete-modal-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .callcenter-mobile-delete-modal-actions button {
                min-height: 42px;
                border-radius: 9px;
                font-size: 16px;
                font-weight: 700;
            }

            .callcenter-mobile-delete-modal-cancel {
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #111827;
            }

            .callcenter-mobile-delete-modal-confirm {
                border: 1px solid #dc2626;
                background: #dc2626;
                color: #ffffff;
            }

            .callcenter-mobile-order-card-bottom {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                gap: 5px;
                align-items: center;
                margin-top: 6px;
            }

            .callcenter-mobile-order-price,
            .callcenter-mobile-order-total {
                min-width: 0;
                overflow: hidden;
                font-size: 12px;
                line-height: 1.2;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .callcenter-mobile-order-total {
                color: #0369a1;
                font-weight: 700;
                text-align: right;
            }

            .callcenter-mobile-order-price s {
                color: #94a3b8;
            }

            .callcenter-mobile-order-price input,
            .callcenter-mobile-order-discount input {
                box-sizing: border-box;
                width: 72px;
                max-width: 72px;
                height: 28px;
                padding: 2px 5px;
                border: 1px solid #94a3b8;
                border-radius: 4px;
                font-size: 12px;
                text-align: right;
            }

            .callcenter-mobile-order-editable {
                display: inline-block;
                min-height: 28px;
                cursor: pointer;
            }

            .callcenter-mobile-order-discount {
                cursor: text;
            }

            .callcenter-mobile-order-qty {
                display: inline-flex;
                gap: 2px;
                align-items: center;
            }

            .callcenter-mobile-order-qty button,
            .callcenter-mobile-order-qty input {
                box-sizing: border-box;
                height: 40px;
                min-height: 40px;
                padding: 0;
                border: 1px solid #cbd5e1;
                border-radius: 5px;
                text-align: center;
            }

            .callcenter-mobile-order-qty button {
                width: 40px;
                min-width: 40px;
                background: #f8fafc;
                font-size: 17px;
            }

            .callcenter-mobile-order-qty input {
                width: 34px;
                min-width: 34px;
                appearance: textfield;
                -moz-appearance: textfield;
            }

            .callcenter-mobile-order-qty input::-webkit-outer-spin-button,
            .callcenter-mobile-order-qty input::-webkit-inner-spin-button {
                appearance: none;
            }

            .callcenter-mobile-order-discount {
                margin-top: 3px;
                color: #be123c;
                font-size: 11px;
                white-space: nowrap;
            }

            .callcenter-discovery-details {
                border-top: 1px solid #fecdd3;
            }

            .callcenter-discovery-summary {
                min-height: 40px;
                padding: 4px 10px;
            }

            .callcenter-discovery-summary button:last-of-type {
                margin-left: auto;
            }

            .callcenter-discovery-child {
                grid-template-columns: 24px minmax(0, 1fr) 42px;
                min-height: 64px;
                padding: 6px 10px;
            }

            .callcenter-discovery-child-name {
                display: grid;
                grid-template-columns: 48px minmax(0, 1fr);
                grid-template-rows: auto auto;
                column-gap: 8px;
                min-width: 0;
                align-items: center;
            }

            .callcenter-discovery-child-name .callcenter-order-product-line {
                display: contents;
            }

            .callcenter-discovery-child-name .callcenter-order-hover-image {
                grid-column: 1;
                grid-row: 1 / span 2;
                width: 48px;
                height: 58px;
                flex-basis: 48px;
            }

            .callcenter-discovery-child-name .callcenter-order-hover-image img {
                width: 46px !important;
                height: 58px !important;
                max-width: 46px !important;
                max-height: 58px !important;
            }

            .callcenter-discovery-child-name strong {
                grid-column: 2;
                grid-row: 1;
                min-width: 0;
                align-self: end;
            }

            .callcenter-discovery-child-name > span {
                grid-column: 2;
                grid-row: 2;
                align-self: start;
                color: #64748b;
                font-size: 11px;
                line-height: 1.2;
                text-align: center;
                white-space: nowrap;
            }

            .callcenter-discovery-child-name > span::after {
                content: ' · 3 мл';
            }

            .callcenter-discovery-child-volume {
                display: none;
            }

            .callcenter-discovery-child-need {
                grid-column: 3;
                grid-row: 1 / span 2;
                white-space: nowrap;
            }
        }
    </style>

    <script>
        (function () {
            if (typeof window.textareaFormComponent !== 'function') {
                window.textareaFormComponent = function (config = {}) {
                    return {
                        state: config.state ?? '',
                        shouldAutosize: !!(config.shouldAutosize ?? false),
                        initialHeight: Number(config.initialHeight ?? 0),
                        init() {},
                        autosize() {},
                    };
                };
            }

            const TEXT_EMPTY = '-';

            function formatOneDecimal(value) {
                const normalized = String(value).replace(',', '.').trim();

                if (normalized === '') {
                    return '';
                }

                const number = Number(normalized);

                if (Number.isNaN(number)) {
                    return String(value);
                }

                return number.toFixed(1).replace('.', ',');
            }

            function formatTwoDecimals(value) {
                const normalized = String(value).replace(',', '.').trim();

                if (normalized === '') {
                    return '';
                }

                const number = Number(normalized);

                if (Number.isNaN(number)) {
                    return String(value);
                }

                return number.toFixed(2).replace('.', ',');
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            window.callcenterSaveKitchenNote = function (el) {
                const row = el?.closest('tr')
                    || el?.closest('.table-repeater-row')
                    || el?.closest('[data-repeater-item]')
                    || el?.closest('.table-repeater-container')
                    || document;
                const select = row.querySelector('select[name$="[product_id]"]') || row.querySelector('select[name*="product_id"]');
                const idInput = row.querySelector('input[name$="[id]"]') || row.querySelector('input[name*="[id]"]');
                const orderItemId = Number(el?.dataset?.orderItemId || idInput?.value || 0);
                let input = row.querySelector('input[name$="[kitchen_note]"]')
                    || row.querySelector('input[name*="kitchen_note"]');

                if (!input && select?.name && typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                    const expectedName = select.name.replace('[product_id]', '[kitchen_note]');
                    input = document.querySelector(`input[name="${CSS.escape(expectedName)}"]`);
                }

                const popover = el?.closest('.callcenter-kitchen-note-popover');
                const textarea = popover ? popover.querySelector('.callcenter-kitchen-note-textarea') : null;
                const noteValue = String(textarea?.value || '').trim();

                if (!input) {
                    return { orderItemId, noteValue };
                }

                input.value = noteValue;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                if (select) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                return { orderItemId, noteValue };
            };

            window.callcenterHandleKitchenNoteSave = function (el, wire) {
                const payload = window.callcenterSaveKitchenNote(el) || {};
                const orderItemId = Number(payload.orderItemId || 0);
                const noteValue = String(payload.noteValue || '');

                if (!wire || orderItemId <= 0) {
                    return;
                }

                if (typeof wire.persistKitchenNoteInline === 'function') {
                    wire.persistKitchenNoteInline(orderItemId, noteValue);
                    return;
                }

                if (typeof wire.call === 'function') {
                    wire.call('persistKitchenNoteInline', orderItemId, noteValue);
                }
            };

            function resolveKitchenNoteInput(host, sourceSelect) {
                const row = host.closest('tr')
                    || host.closest('.table-repeater-row')
                    || host.closest('[data-repeater-item]')
                    || host.parentElement;
                let noteInput = row
                    ? (
                        row.querySelector('input[name$="[kitchen_note]"]')
                        || row.querySelector('input[name*="kitchen_note"]')
                    )
                    : null;

                if (!noteInput && sourceSelect?.name && typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                    const expectedName = sourceSelect.name.replace('[product_id]', '[kitchen_note]');
                    noteInput = document.querySelector(`input[name="${CSS.escape(expectedName)}"]`);
                }

                return noteInput;
            }

            function makeDisplayElement(target, className) {
                const host = target || null;
                if (!host) {
                    return null;
                }

                let display = host.querySelector(`.${className}`);

                if (!display) {
                    display = document.createElement('span');
                    display.className = `callcenter-inline-display ${className}`;
                    host.appendChild(display);
                }

                return display;
            }

            function setupInput(input) {
                if (!input || input.dataset.inlineInit === '1') {
                    return;
                }

                input.dataset.inlineInit = '1';

                const editableWrapper = input.closest('.callcenter-inline-editable-wrapper');
                const host = editableWrapper || input.parentElement;
                const display = makeDisplayElement(host, 'callcenter-inline-input-display');
                const editorContainer = editableWrapper?.querySelector('.fi-input-wrp') || input;
                const isPriceInput = input.classList.contains('callcenter-inline-input-price');
                const isQtyInput = input.classList.contains('callcenter-inline-input-qty');
                const isDiscountInput = input.classList.contains('callcenter-inline-input-discount');

                if (!display || !editorContainer) {
                    return;
                }

                if (isQtyInput) {
                    display.classList.add('is-qty-display');
                }

                if (isDiscountInput) {
                    display.classList.add('is-discount-display');
                }

                const updateDisplay = () => {
                    const value = (input.value || '').trim();
                    const formatted = isPriceInput
                        ? formatOneDecimal(value)
                        : isDiscountInput
                            ? formatTwoDecimals(value)
                        : value;

                    display.textContent = formatted || TEXT_EMPTY;
                    display.classList.toggle('is-empty', formatted === '');
                };

                const updateDisplayDeferred = () => {
                    updateDisplay();
                    setTimeout(updateDisplay, 60);
                    setTimeout(updateDisplay, 180);
                    setTimeout(updateDisplay, 450);
                };

                const openEditor = () => {
                    if (isPriceInput) {
                        const formatted = formatOneDecimal(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    if (isDiscountInput) {
                        const formatted = formatTwoDecimals(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    display.classList.add('callcenter-inline-hidden');
                    editorContainer.classList.remove('callcenter-inline-hidden');
                    if (editableWrapper) {
                        editableWrapper.classList.add('is-editing');
                    }
                    input.focus();
                    input.select();
                };

                const closeEditor = () => {
                    if (isPriceInput) {
                        const formatted = formatOneDecimal(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    if (isDiscountInput) {
                        const formatted = formatTwoDecimals(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    updateDisplay();
                    editorContainer.classList.add('callcenter-inline-hidden');
                    display.classList.remove('callcenter-inline-hidden');
                    if (editableWrapper) {
                        editableWrapper.classList.remove('is-editing');
                    }
                };

                display.addEventListener(isQtyInput ? 'click' : 'dblclick', openEditor);

                input.addEventListener('input', updateDisplay);
                input.addEventListener('change', updateDisplay);
                input.addEventListener('blur', closeEditor);
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        input.blur();
                    }

                    if (event.key === 'Escape') {
                        input.blur();
                    }
                });

                const syncObserver = new MutationObserver(() => {
                    updateDisplay();
                });

                syncObserver.observe(input, {
                    attributes: true,
                    attributeFilter: ['value'],
                });

                closeEditor();
                updateDisplayDeferred();
            }

            function getSelectText(sourceSelect, editorContainer) {
                if (sourceSelect && sourceSelect.selectedOptions && sourceSelect.selectedOptions.length > 0) {
                    const text = (sourceSelect.selectedOptions[0].textContent || '').trim();
                    if (text !== '') {
                        return text;
                    }
                }

                if (editorContainer) {
                    const item = editorContainer.querySelector('.choices__item.choices__item--selectable');
                    if (item) {
                        return (item.textContent || '').trim();
                    }
                }

                return '';
            }

            function setupSelect(host) {
                if (!host || host.dataset.inlineInit === '1') {
                    return;
                }

                const sourceSelect = host.querySelector('select');
                const editorContainer = host.querySelector('div[wire\\:ignore]') || sourceSelect;
                if (!sourceSelect || !editorContainer) {
                    return;
                }

                host.dataset.inlineInit = '1';

                const display = makeDisplayElement(host, 'callcenter-inline-select-display');

                const updateDisplay = () => {
                    const value = getSelectText(sourceSelect, editorContainer);
                    const noteInput = resolveKitchenNoteInput(host, sourceSelect);
                    const note = (noteInput?.value || '').trim();

                    if (note !== '') {
                        const title = value || TEXT_EMPTY;

                        display.classList.add('has-note');
                        display.innerHTML = `
                            <span class="block truncate overflow-hidden text-ellipsis">${escapeHtml(title)}</span>
                            <span class="callcenter-inline-select-display-note">${escapeHtml(note)}</span>
                        `;
                    } else {
                        display.classList.remove('has-note');
                        display.textContent = value || TEXT_EMPTY;
                    }

                    display.classList.toggle('is-empty', value === '');
                };

                const updateDisplayDeferred = () => {
                    updateDisplay();
                    setTimeout(updateDisplay, 80);
                    setTimeout(updateDisplay, 250);
                    setTimeout(updateDisplay, 700);
                };

                const openEditor = () => {
                    display.classList.add('callcenter-inline-hidden');
                    editorContainer.classList.remove('callcenter-inline-hidden');

                    const input = editorContainer.querySelector('input');
                    if (input) {
                        input.focus();
                    }
                };

                const closeEditor = () => {
                    updateDisplay();
                    editorContainer.classList.add('callcenter-inline-hidden');
                    display.classList.remove('callcenter-inline-hidden');
                };

                display.addEventListener('click', openEditor);

                sourceSelect.addEventListener('change', () => {
                    updateDisplay();
                    closeEditor();
                });

                sourceSelect.addEventListener('input', updateDisplay);

                const bindNoteListeners = () => {
                    const noteInput = resolveKitchenNoteInput(host, sourceSelect);

                    if (!noteInput || noteInput.dataset.noteBound === '1') {
                        return;
                    }

                    noteInput.dataset.noteBound = '1';
                    noteInput.addEventListener('input', updateDisplay);
                    noteInput.addEventListener('change', updateDisplay);
                };

                bindNoteListeners();

                const syncObserver = new MutationObserver(() => {
                    bindNoteListeners();
                    updateDisplay();
                });

                syncObserver.observe(editorContainer, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                });

                editorContainer.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeEditor();
                    }
                });

                document.addEventListener('mousedown', (event) => {
                    if (display.classList.contains('callcenter-inline-hidden') && !host.contains(event.target)) {
                        closeEditor();
                    }
                });

                closeEditor();
                updateDisplayDeferred();
            }

            function initInlineEditors(root = document) {
                root.querySelectorAll('.callcenter-items-table .callcenter-inline-input-price, .callcenter-items-table .callcenter-inline-input-qty, .callcenter-items-table .callcenter-inline-input-discount').forEach(setupInput);
                root.querySelectorAll('.callcenter-items-table .callcenter-inline-select').forEach(setupSelect);
            }

            function setupPhoneAutocomplete(input) {
                if (!input || input.dataset.ccPhoneInit === '1') {
                    return;
                }

                input.dataset.ccPhoneInit = '1';

                let debounceTimer = null;
                let requestSeq = 0;
                let lastMirroredDigits = '';

                const clientHost = document.querySelector('.callcenter-client-select');
                if (!clientHost) {
                    return;
                }

                let sidePanel = clientHost.querySelector('.callcenter-client-side-results');

                if (!sidePanel) {
                    sidePanel = document.createElement('div');
                    sidePanel.className = 'callcenter-client-side-results';
                    clientHost.appendChild(sidePanel);
                }

                const closePanel = () => {
                    sidePanel.style.display = 'none';
                    sidePanel.innerHTML = '';
                };

                const closeClientDropdown = () => {
                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const choicesEl = clientHost.querySelector('.choices');
                    const dropdownEl = clientHost.querySelector('.choices__list--dropdown');

                    if (alpineData?.select && typeof alpineData.select.hideDropdown === 'function') {
                        alpineData.select.hideDropdown();
                    }

                    choicesEl?.classList.remove('is-open');
                    dropdownEl?.classList.remove('is-active');
                };

                const selectClient = (item) => {
                    if (!item || !item.id) {
                        return;
                    }

                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const stateSelect = wireIgnore?.querySelector('select');

                    input.value = String(item.phone || '');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));

                    if (alpineData?.select) {
                        alpineData.select.setChoiceByValue(String(item.id));
                    }

                    if (stateSelect) {
                        stateSelect.value = String(item.id);
                        stateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    closePanel();
                };

                const renderPanel = (items) => {
                    sidePanel.innerHTML = '';

                    if (!Array.isArray(items) || items.length === 0) {
                        closePanel();
                        return;
                    }

                    for (const item of items) {
                        const row = document.createElement('div');
                        row.className = 'callcenter-client-side-item';

                        const phone = document.createElement('span');
                        phone.className = 'callcenter-client-side-item-phone';
                        phone.textContent = String(item.phone || '');

                        const name = document.createElement('span');
                        name.className = 'callcenter-client-side-item-name';
                        name.textContent = String(item.name || '');

                        row.appendChild(phone);
                        row.appendChild(name);
                        row.addEventListener('mousedown', (event) => event.preventDefault());
                        row.addEventListener('click', () => selectClient(item));

                        sidePanel.appendChild(row);
                    }

                    sidePanel.style.display = 'block';
                };

                const mirrorSearchToClientSelect = (digits) => {
                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const stateSelect = wireIgnore?.querySelector('select');

                    if (!alpineData?.select) {
                        return;
                    }

                    if (digits === lastMirroredDigits) {
                        return;
                    }

                    lastMirroredDigits = digits;

                    if (typeof alpineData.refreshChoices === 'function') {
                        alpineData.refreshChoices({
                            search: digits,
                            withInitialOptions: false,
                        });
                    }

                    const clientSearchInput = alpineData.select.input?.element;
                    if (clientSearchInput) {
                        clientSearchInput.value = digits;
                        clientSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    if (stateSelect) {
                        stateSelect.dispatchEvent(new CustomEvent('search', {
                            detail: { value: digits },
                            bubbles: true,
                        }));
                    }

                    window.setTimeout(() => {
                        const choicesEl = clientHost.querySelector('.choices');
                        const dropdownEl = clientHost.querySelector('.choices__list--dropdown');

                        choicesEl?.classList.add('is-open');
                        dropdownEl?.classList.add('is-active');
                    }, 50);
                };

                const syncToClientSelect = () => {
                    const digits = String(input.value || '').replace(/\D+/g, '');

                    if (digits.length < 3) {
                        closePanel();
                        lastMirroredDigits = '';
                        closeClientDropdown();

                        return;
                    }

                    mirrorSearchToClientSelect(digits);

                    const seq = ++requestSeq;

                    fetch(`/admin/callcenter/clients/phone-suggestions?q=${encodeURIComponent(digits)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then((response) => (response.ok ? response.json() : []))
                        .then((items) => {
                            if (seq !== requestSeq) {
                                return;
                            }

                            renderPanel(items);
                        })
                        .catch(() => {
                            closePanel();
                        });
                };

                const scheduleSync = () => {
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(syncToClientSelect, 180);
                };

                input.addEventListener('input', scheduleSync);
                input.addEventListener('focus', scheduleSync);
                input.addEventListener('blur', () => {
                    window.setTimeout(closePanel, 150);
                    window.setTimeout(closeClientDropdown, 150);
                });
            }

            function initPhoneAutocomplete(root = document) {
                root.querySelectorAll('.callcenter-phone-input').forEach(setupPhoneAutocomplete);
            }

            function applyHistoryAddressToDom(payload) {
                if (!payload || typeof payload !== 'object') {
                    console.info('History apply: invalid payload', payload);
                    return;
                }

                const address = payload.address || {};
                const selectedAddressId = String(payload.selectedAddressId ?? '-1');

                const findField = (variants) => {
                    for (const variant of variants) {
                        const byName = document.querySelector(`[name="${variant}"]`);
                        if (byName) return byName;

                        const byContains = document.querySelector(`[name*="${variant}"]`);
                        if (byContains) return byContains;
                    }

                    return null;
                };

                const setFieldValue = (nameVariants, value) => {
                    const el = findField(Array.isArray(nameVariants) ? nameVariants : [nameVariants]);
                    if (!el) {
                        console.info('History apply: field NOT found', nameVariants);
                        return;
                    }

                    console.info('History apply: set field', {
                        name: el.getAttribute('name'),
                        id: el.getAttribute('id'),
                        value,
                    });

                    el.value = value ?? '';
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                };

                console.info('History apply: start', {
                    selectedAddressId,
                    address,
                    shippingPrice: payload.shippingPrice,
                });

                const findLivewireComponent = () => {
                    if (payload.componentId && window.Livewire && typeof window.Livewire.find === 'function') {
                        const byPayload = window.Livewire.find(payload.componentId);
                        if (byPayload) {
                            return byPayload;
                        }
                    }

                    const host = document.querySelector('.callcenter-phone-input')?.closest('[wire\\:id]')
                        || document.querySelector('[wire\\:id]');

                    if (!host) {
                        return null;
                    }

                    const id = host.getAttribute('wire:id');
                    if (!id || !window.Livewire || typeof window.Livewire.find !== 'function') {
                        return null;
                    }

                    return window.Livewire.find(id);
                };

                const livewire = findLivewireComponent();

                if (livewire && typeof livewire.set === 'function') {
                    console.info('History apply: using Livewire.set');

                    livewire.set('data.self_pickup', false);
                    livewire.set('data.selected_address_id', selectedAddressId);
                    livewire.set('data.address.street', address.street ?? '');
                    livewire.set('data.address.street_place_id', address.street_place_id ?? address.street ?? '');
                    livewire.set('data.address.house', address.house ?? '');
                    livewire.set('data.address.apartment', address.apartment ?? '');
                    livewire.set('data.address.entrance', address.entrance ?? '');
                    livewire.set('data.address.intercom', address.intercom ?? '');
                    livewire.set('data.address.floor', address.floor ?? '');
                    livewire.set('data.address.city', address.city ?? 'Київ');
                    livewire.set('data.address.note', address.note ?? '');
                    livewire.set('data.address.latitude', address.latitude ?? null);
                    livewire.set('data.address.longitude', address.longitude ?? null);
                    livewire.set('data.address.formatted_address', address.formatted_address ?? null);
                    livewire.set('data.address.type', address.type ?? null);
                    livewire.set('data.address.is_private_house', !!address.is_private_house);

                    if (payload.shippingPrice !== undefined) {
                        livewire.set('data.shipping_price', payload.shippingPrice);
                    }

                    // Триггерим перерисовку/пересчет зависимых полей.
                    livewire.set('data.delivery_coords_trigger', 'history_front_' + Date.now());
                } else {
                    console.info('History apply: Livewire component not found, fallback DOM only');
                }

                const allFormFields = Array.from(document.querySelectorAll('input[name], select[name], textarea[name]'))
                    .map((el) => ({
                        tag: el.tagName,
                        name: el.getAttribute('name'),
                        id: el.getAttribute('id'),
                    }));
                console.info('History apply: visible form fields', allFormFields);

                setFieldValue(['selected_address_id', 'data.selected_address_id'], selectedAddressId);
                setFieldValue(['address.street', 'data.address.street', '[address][street]'], address.street ?? '');
                setFieldValue(['address.street_place_id', 'data.address.street_place_id', '[address][street_place_id]'], address.street_place_id ?? address.street ?? '');
                setFieldValue(['address.house', 'data.address.house', '[address][house]'], address.house ?? '');
                setFieldValue(['address.apartment', 'data.address.apartment', '[address][apartment]'], address.apartment ?? '');
                setFieldValue(['address.entrance', 'data.address.entrance', '[address][entrance]'], address.entrance ?? '');
                setFieldValue(['address.intercom', 'data.address.intercom', '[address][intercom]'], address.intercom ?? '');
                setFieldValue(['address.floor', 'data.address.floor', '[address][floor]'], address.floor ?? '');
                setFieldValue(['address.city', 'data.address.city', '[address][city]'], address.city ?? 'Київ');
                setFieldValue(['address.note', 'data.address.note', '[address][note]'], address.note ?? '');
                setFieldValue(['address.latitude', 'data.address.latitude', '[address][latitude]'], address.latitude ?? '');
                setFieldValue(['address.longitude', 'data.address.longitude', '[address][longitude]'], address.longitude ?? '');
                setFieldValue(['address.formatted_address', 'data.address.formatted_address', '[address][formatted_address]'], address.formatted_address ?? '');
                setFieldValue(['address.type', 'data.address.type', '[address][type]'], address.type ?? '');

                const privateHouseToggle = findField([
                    'address.is_private_house',
                    'data.address.is_private_house',
                    '[address][is_private_house]',
                ]);
                if (privateHouseToggle) {
                    privateHouseToggle.checked = !!address.is_private_house;
                    privateHouseToggle.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const shipping = findField(['shipping_price', 'data.shipping_price']);
                if (shipping && payload.shippingPrice !== undefined) {
                    console.info('History apply: set shipping', {
                        name: shipping.getAttribute('name'),
                        id: shipping.getAttribute('id'),
                        value: payload.shippingPrice,
                    });
                    shipping.value = payload.shippingPrice;
                    shipping.dispatchEvent(new Event('input', { bubbles: true }));
                    shipping.dispatchEvent(new Event('change', { bubbles: true }));
                }

                console.info('History apply: done');
            }

            const onHistoryAddressApplied = (event) => {
                console.info('History apply: event received', event?.detail);
                applyHistoryAddressToDom(event?.detail || {});
            };

            window.addEventListener('callcenter-history-address-applied', onHistoryAddressApplied);
            document.addEventListener('callcenter-history-address-applied', onHistoryAddressApplied);

            document.addEventListener('livewire:init', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);

                const observer = new MutationObserver(() => {
                    initInlineEditors(document);
                    initPhoneAutocomplete(document);
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            });

            document.addEventListener('filament:form-mounted', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);
            });

            document.addEventListener('DOMContentLoaded', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);
            });
        })();
    </script>
@endif

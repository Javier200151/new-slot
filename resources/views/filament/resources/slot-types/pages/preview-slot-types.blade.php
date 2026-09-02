<x-filament-panels::page>
    <script>
        window.newSlotTypePickerPreview = (initialColumns) => ({
            columns: initialColumns,
            dragging: null,
            dirty: false,
            saving: false,

            dragStart(columnIndex, itemIndex) {
                this.dragging = {
                    columnIndex,
                    itemIndex,
                };
            },

            dropAt(targetColumnIndex, targetItemIndex) {
                if (!this.dragging) {
                    return;
                }

                const source = this.dragging;
                const [item] = this.columns[source.columnIndex].splice(
                    source.itemIndex,
                    1,
                );

                if (
                    source.columnIndex === targetColumnIndex
                    && source.itemIndex < targetItemIndex
                ) {
                    targetItemIndex--;
                }

                this.columns[targetColumnIndex].splice(
                    targetItemIndex,
                    0,
                    item,
                );

                this.dragging = null;
                this.dirty = true;
            },

            dropAtEnd(targetColumnIndex) {
                if (!this.dragging) {
                    return;
                }

                const source = this.dragging;
                const [item] = this.columns[source.columnIndex].splice(
                    source.itemIndex,
                    1,
                );

                this.columns[targetColumnIndex].push(item);
                this.dragging = null;
                this.dirty = true;
            },

            async save() {
                if (this.saving || !this.dirty) {
                    return;
                }

                this.saving = true;

                try {
                    await this.$wire.saveLayout(
                        this.columns.map(
                            column => column.map(item => item.id)
                        )
                    );
                    this.dirty = false;
                } finally {
                    this.saving = false;
                }
            },
        });
    </script>

    <div
        x-data="newSlotTypePickerPreview(@js($columns))"
        class="slot-picker-preview"
    >
        <div class="slot-picker-preview__intro">
            <div>
                <strong>Orden global del selector ORBAT</strong>
                <p>
                    Arrastra las tarjetas entre las cuatro columnas. Este será
                    el mismo orden que verán todos los usuarios al pulsar
                    “Escoger slot”.
                </p>
            </div>

            <x-filament::button
                type="button"
                icon="heroicon-o-check"
                x-on:click="save()"
                x-bind:disabled="saving || !dirty"
            >
                <span x-show="!saving">Guardar orden</span>
                <span x-show="saving">Guardando...</span>
            </x-filament::button>
        </div>

        <div class="slot-picker-preview__grid">
            <template
                x-for="(column, columnIndex) in columns"
                :key="columnIndex"
            >
                <div
                    class="slot-picker-preview__column"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dropAtEnd(columnIndex)"
                >
                    <template
                        x-for="(slotType, itemIndex) in column"
                        :key="slotType.id"
                    >
                        <article
                            class="slot-picker-preview__card"
                            draggable="true"
                            x-on:dragstart.stop="dragStart(columnIndex, itemIndex)"
                            x-on:dragend="dragging = null"
                            x-on:dragover.prevent.stop
                            x-on:drop.prevent.stop="dropAt(columnIndex, itemIndex)"
                        >
                            <header class="slot-picker-preview__card-header">
                                <span class="slot-picker-preview__drag">⋮⋮</span>
                                <strong x-text="slotType.name"></strong>
                            </header>

                            <div class="slot-picker-preview__quick-names">
                                <template
                                    x-for="quickName in slotType.quick_names"
                                    :key="quickName"
                                >
                                    <span
                                        class="slot-picker-preview__quick-name"
                                        x-text="quickName"
                                    ></span>
                                </template>
                            </div>
                        </article>
                    </template>

                    <div class="slot-picker-preview__drop-zone">
                        Soltar aquí
                    </div>
                </div>
            </template>
        </div>
    </div>

    <style>
        .slot-picker-preview__intro {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .8rem;
            background: rgba(24, 24, 27, .72);
        }

        .slot-picker-preview__intro p {
            margin-top: .3rem;
            color: rgb(161, 161, 170);
            font-size: .875rem;
        }

        .slot-picker-preview__grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .9rem;
            align-items: start;
        }

        .slot-picker-preview__column {
            display: flex;
            flex-direction: column;
            gap: .9rem;
            min-height: 10rem;
        }

        .slot-picker-preview__card {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .7rem;
            background: rgb(24, 24, 27);
            cursor: grab;
        }

        .slot-picker-preview__card:active {
            cursor: grabbing;
        }

        .slot-picker-preview__card-header {
            display: flex;
            gap: .5rem;
            align-items: center;
            padding: .65rem .75rem;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .slot-picker-preview__drag {
            color: rgb(245, 158, 11);
            font-weight: 800;
            letter-spacing: -.2rem;
        }

        .slot-picker-preview__quick-names {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
            padding: .75rem;
        }

        .slot-picker-preview__quick-name {
            display: inline-flex;
            padding: .35rem .55rem;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: .45rem;
            background: rgba(63, 63, 70, .5);
            font-size: .78rem;
        }

        .slot-picker-preview__drop-zone {
            display: flex;
            min-height: 3rem;
            align-items: center;
            justify-content: center;
            border: 1px dashed rgba(245, 158, 11, .35);
            border-radius: .65rem;
            color: rgba(245, 158, 11, .65);
            font-size: .78rem;
        }

        @media (max-width: 1100px) {
            .slot-picker-preview__grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .slot-picker-preview__intro {
                align-items: stretch;
                flex-direction: column;
            }

            .slot-picker-preview__grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-filament-panels::page>

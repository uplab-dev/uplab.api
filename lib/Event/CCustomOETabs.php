<?php

namespace Uplab\Api\Event;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Uplab\Api\Model\Iblock\PageService;

/**
 * @link https://dev.1c-bitrix.ru/api_help/main/general/admin.section/tabengine.php
 */
class CCustomOETabs
{
	public static function OnInit($params)
	{
		if (($params['IBLOCK']['CODE'] ?? '') == PageService::IBLOCK_CODE) {
			return [
					"TABSET"  => "YYY",
					"GetTabs" => [static::class, "GetTabs"],
					"ShowTab" => [static::class, "ShowTab"],
					"Action"  => [static::class, "Action"],
					"Check"   => [static::class, "Check"]
			];
		}
		return false;
	}

	public static function Action($arArgs)
	{
		return true;
		// Основные данные сохранены. Делаем тут действия.
		// Возвращаем True в случае успеха и False - в случае ошибки
		// В случае ошибки делаем так же $GLOBALS["APPLICATION"]-> ThrowException("Ошибка!!!", "ERROR");
	}

	public static function Check($arArgs)
	{
		return true;
		// Основные данные ещё не сохранялись. Делаем тут разные чеки.
		// Возвращаем True, если можно все схранять, иначе False
		// В случае False делаем так же $GLOBALS["APPLICATION"]-> ThrowException("Ошибка!!!", "ERROR");
	}

	public static function GetTabs($arArgs)
	{
		// SORT - после какого стандартного таба вставлять. Не установлено - после последнего
		$arTabs = [
				[
						"DIV"   => "UplabApiIblockElementHlContentTab",
						"TAB"   => Loc::getMessage('HL_CONTENT_TAB_NAME'),
						"ICON"  => "sale",
						"TITLE" => Loc::getMessage('HL_CONTENT_TAB_TITLE'),
						"SORT"  => 1
				]
		];
		return $arTabs;
	}

	public static function ShowTab($divName, $arArgs, $bVarsFromForm)
	{
		if ($divName == "UplabApiIblockElementHlContentTab" && !empty($arArgs['ID']) && Loader::includeModule('iblock')) {
			$arItem = \CIBlockElement::GetByID($arArgs['ID'])->Fetch();

			\Bitrix\Main\UI\Extension::load('ui.vue3');
			\Bitrix\Main\UI\Extension::load('ui.buttons');
			\Bitrix\Main\UI\Extension::load('ui.buttons.icons');
			\Bitrix\Main\UI\Extension::load('ui.alerts');
			\Bitrix\Main\UI\Extension::load('ui.notification');
			\CJSCore::init(["sidepanel", "ui.forms"]);
			Loc::loadMessages(__FILE__);
			$hlctLang = [
				'refresh'           => Loc::getMessage('HL_CONTENT_TAB_BTN_REFRESH'),
				'addElement'        => Loc::getMessage('HL_CONTENT_TAB_BTN_ADD'),
				'addPanelTitle'     => Loc::getMessage('HL_CONTENT_TAB_ADD_PANEL_TITLE'),
				'editPanelTitle'    => Loc::getMessage('HL_CONTENT_TAB_EDIT_PANEL_TITLE'),
				'closePanel'        => Loc::getMessage('HL_CONTENT_TAB_CLOSE_PANEL'),
				'menuEdit'          => Loc::getMessage('HL_CONTENT_TAB_MENU_EDIT'),
				'menuActivate'      => Loc::getMessage('HL_CONTENT_TAB_MENU_ACTIVATE'),
				'menuDeactivate'    => Loc::getMessage('HL_CONTENT_TAB_MENU_DEACTIVATE'),
				'menuDelete'        => Loc::getMessage('HL_CONTENT_TAB_MENU_DELETE'),
				'confirmDelete'     => Loc::getMessage('HL_CONTENT_TAB_CONFIRM_DELETE'),
				'bulkSelected'      => Loc::getMessage('HL_CONTENT_TAB_BULK_SELECTED'),
				'bulkActivate'      => Loc::getMessage('HL_CONTENT_TAB_BULK_ACTIVATE'),
				'bulkDeactivate'    => Loc::getMessage('HL_CONTENT_TAB_BULK_DEACTIVATE'),
				'bulkDelete'        => Loc::getMessage('HL_CONTENT_TAB_BULK_DELETE'),
				'bulkDeselect'      => Loc::getMessage('HL_CONTENT_TAB_BULK_DESELECT'),
				'bulkDeleteConfirm' => Loc::getMessage('HL_CONTENT_TAB_BULK_DELETE_CONFIRM'),
				'notifySuccess'     => Loc::getMessage('HL_CONTENT_TAB_NOTIFY_SUCCESS'),
				'notifyError'       => Loc::getMessage('HL_CONTENT_TAB_NOTIFY_ERROR'),
				'empty'             => Loc::getMessage('HL_CONTENT_TAB_EMPTY'),
				'pageSize'          => Loc::getMessage('HL_CONTENT_TAB_PAGE_SIZE'),
				'showing'           => Loc::getMessage('HL_CONTENT_TAB_SHOWING'),
				'of'                => Loc::getMessage('HL_CONTENT_TAB_OF'),
				'items'             => Loc::getMessage('HL_CONTENT_TAB_ITEMS'),
				'goTo'              => Loc::getMessage('HL_CONTENT_TAB_GOTO'),
				'firstPage'         => Loc::getMessage('HL_CONTENT_TAB_FIRST_PAGE'),
				'lastPage'          => Loc::getMessage('HL_CONTENT_TAB_LAST_PAGE'),
				'prev'              => Loc::getMessage('HL_CONTENT_TAB_PREV'),
				'next'              => Loc::getMessage('HL_CONTENT_TAB_NEXT'),
				'statusActive'      => Loc::getMessage('HL_CONTENT_TAB_STATUS_ACTIVE'),
				'statusInactive'    => Loc::getMessage('HL_CONTENT_TAB_STATUS_INACTIVE'),
				'deleteSuccess'     => Loc::getMessage('HL_CONTENT_TAB_DELETE_SUCCESS'),
				'deleteError'       => Loc::getMessage('HL_CONTENT_TAB_DELETE_ERROR'),
			];
			$pageCodeJson = json_encode(
				(string)($arItem['CODE'] ?? ''),
				JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			);
			?>
			<div id="vue-elements-list-app"></div>

			<script>
				const HLCT = <?= json_encode($hlctLang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
				const HLCT_PAGE_CODE = <?= $pageCodeJson ?>;
				BX.ready(function () {
					const app = BX.Vue3.BitrixVue.createApp({
						data() {
							return {
								lang: HLCT,
								elements: [],
								headers: [],
								isLoading: true,
								error: null,
								selectedIds: [],
								pagination: {
									page: 1,
									pageSize: 20,
									total: 0,
									maxVisibleButtons: 5
								}
							};
						},
						components: {},
						computed: {
							skeletonColCount() {
								return this.headers.length > 0 ? this.headers.length + 2 : 5;
							},
							skeletonRowCount() {
								return Math.min(this.pagination.pageSize, 6);
							},
							allSelected() {
								return this.elements.length > 0 && this.selectedIds.length === this.elements.length;
							},
							someSelected() {
								return this.selectedIds.length > 0 && this.selectedIds.length < this.elements.length;
							},
							isShowNavigation() {
								return !!(this.elements && this.pagination.total);
							},
							isInFirstPage() {
								return this.pagination.page === 1
							},
							totalPages() {
								return Math.ceil(this.pagination.total / this.pagination.pageSize);
							},

							shouldShowPagination() {
								return this.pagination.total > 0 && this.totalPages > 1;
							},

							showingStart() {
								return ((this.pagination.page - 1) * this.pagination.pageSize) + 1;
							},

							showingEnd() {
								const end = this.pagination.page * this.pagination.pageSize;
								return Math.min(end, this.pagination.total);
							},

							visiblePages() {
								if (this.totalPages <= 1) return [1];

								const current = this.pagination.page;
								const total = this.totalPages;
								const maxVisible = this.pagination.maxVisibleButtons;

								if (total <= maxVisible) {
									return Array.from({length: total}, (_, i) => i + 1);
								}

								let start = Math.max(1, current - Math.floor(maxVisible / 2));
								let end = Math.min(total, start + maxVisible - 1);

								if (end - start + 1 < maxVisible) {
									start = Math.max(1, end - maxVisible + 1);
								}

								const pages = [];

								// Всегда показываем первую страницу
								if (start > 1) {
									pages.push(1);
									if (start > 2) pages.push('...');
								}

								// Основной диапазон страниц
								for (let i = start; i <= end; i++) {
									pages.push(i);
								}

								// Всегда показываем последнюю страницу
								if (end < total) {
									if (end < total - 1) pages.push('...');
									pages.push(total);
								}

								return pages;
							}
						},
						async mounted() {
							await this.loadElements();
						},
						methods: {
							async addElement() {
								const url = `/bitrix/admin/uplab_api_content_page_edit.php?dialog_type=ADMIN&PAGE=${encodeURIComponent(HLCT_PAGE_CODE)}`
								const self = this;

								BX.SidePanel.Instance.open(url, {
									width: 600,
									requestMethod: "GET",
									Title: HLCT.addPanelTitle,
									autoFocus: true,
									cacheable: false,
									allowChangeHistory: false,
									animationDuration: 300,
									events: {
										onCloseComplete: function () {
											if (window.uplabContentWasSaved) {
												delete window.uplabContentWasSaved;
												self.loadElements();
											}
										}
									},
									label: {
										text: HLCT.closePanel,
										color: "#FFFFFF",
										bgColor: "#E2AE00",
										opacity: 80
									},
								});
							},
							toggleSelectAll() {
								this.selectedIds = this.allSelected ? [] : this.elements.map(el => el.ID);
							},
							showRowMenu(event, element) {
								const self = this;
								const menuId = 'content-row-menu-' + element.ID;
								const existing = BX.Main.MenuManager.getMenuById(menuId);
								if (existing) { existing.destroy(); }
								BX.Main.MenuManager.show({
									id: menuId,
									bindElement: event.currentTarget,
									items: [
										{
											text: HLCT.menuEdit,
											onclick: function(e, item) { item.getMenuWindow().close(); self.editElement(element.ID); }
										},
										{
											text: element.UF_ACTIVE === '1' ? HLCT.menuDeactivate : HLCT.menuActivate,
											onclick: function(e, item) {
												item.getMenuWindow().close();
												self.sendBulk(element.UF_ACTIVE === '1' ? 'deactivate' : 'activate', [element.ID]);
											}
										},
										{ delimiter: true },
										{
											text: HLCT.menuDelete,
											className: 'menu-popup-item-delete',
											onclick: function(e, item) {
												item.getMenuWindow().close();
												if (confirm(HLCT.confirmDelete)) {
													self.sendBulk('delete', [element.ID]);
												}
											}
										}
									]
								});
							},
							sendBulk(action, ids) {
								const self = this;
								BX.ajax({
									url: '/api/content/bulk',
									method: 'POST',
									dataType: 'json',
									data: Object.assign({ action, sessid: BX.bitrix_sessid() }, ids.reduce((acc, id, i) => { acc['ids[' + i + ']'] = id; return acc; }, {})),
									onsuccess: function(res) {
										if (res && res.status === 'error') {
											const msg = (res.errors && res.errors[0]) ? res.errors[0].message : HLCT.notifyError;
											BX.UI.Notification.Center.notify({ content: msg, autoHideDelay: 4000 });
											return;
										}
										self.selectedIds = [];
										self.loadElements();
										BX.UI.Notification.Center.notify({ content: HLCT.notifySuccess, autoHideDelay: 1500 });
									},
									onfailure: function() {
										BX.UI.Notification.Center.notify({ content: HLCT.notifyError, autoHideDelay: 2000 });
									}
								});
							},
							bulkAction(action) {
								if (!this.selectedIds.length) return;
								if (action === 'delete' && !confirm(HLCT.bulkDeleteConfirm.replace('#n', this.selectedIds.length))) return;
								this.sendBulk(action, this.selectedIds);
							},
							async loadElements() {

								try {
									this.isLoading = true;
									this.error = null;

									const response =
										BX.ajax({
											url: '/api/content/admin/' + encodeURIComponent(HLCT_PAGE_CODE),
											data: {
												page: this.pagination.page,
												pageSize: this.pagination.pageSize
											},
											method: 'POST',
											dataType: 'json',
											onsuccess: (res) => {
												if (res.data) {
													this.elements = res.data.items;
													this.headers = res.data.headers;
													this.pagination.total = res.data.total;
													this.isLoading = false;
												}
											},
										});
								} catch (err) {
									console.error('Ошибка загрузки:', err);
									this.error.value = err.message || 'Произошла ошибка при загрузке данных';
								}
							},

							async changePage(page) {
								if (page < 1 || page > this.totalPages || page === '...' || this.isLoading) return;

								this.pagination.page = page;
								await this.loadElements();
								this.scrollToTop();
							},
							async onPageSizeChange() {
								// Сбрасываем на первую страницу при изменении размера
								this.pagination.page = 1;
								await this.loadElements();
								this.scrollToTop();
							},
							scrollToTop() {
								// Плавная прокрутка к верху контейнера
								const container = this.$el.closest('.pagination-container') || this.$el;
								container.scrollIntoView({
									behavior: 'smooth',
									block: 'start',
									inline: 'nearest'
								});
							},

							formatDate(dateString) {
								if (!dateString) return '';
								return new Date(dateString).toLocaleDateString('ru-RU');
							},
							editElement(id) {
								const url = `/bitrix/admin/uplab_api_content_page_edit.php?dialog_type=ADMIN&ID=${id}`
								const self = this;

								BX.SidePanel.Instance.open(url, {
									width: 600,
									requestMethod: "GET",
									Title: HLCT.editPanelTitle,
									autoFocus: true,
									cacheable: false,
									allowChangeHistory: false,
									animationDuration: 300,
									events: {
										onCloseComplete: function () {
											if (window.uplabContentWasSaved) {
												delete window.uplabContentWasSaved;
												self.loadElements();
											}
										}
									},
									label: {
										text: HLCT.closePanel,
										color: "#FFFFFF",
										bgColor: "#E2AE00",
										opacity: 80
									},
								});
							},
							showWaitLoader() {
								BX.showWait('waitloader')
							},
							closeWaitLoader() {
								BX.closeWait('waitloader')
							},
							deleteElement(id) {
								if (!confirm(HLCT.confirmDelete)) {
									return;
								}
								const self = this;
								try {
									BX.ajax({
										url: '/api/content/admin/' + id + '?sessid=' + BX.bitrix_sessid(),
										method: 'DELETE',
										dataType: 'json',
										onsuccess: function (data) {
											if (data && typeof data.status !== "undefined" && data.status === "success") {
												//Удалён послдений элемент на странице
												if (self.pagination.page > 1 && self.elements && self.elements.length === 1) {
													self.pagination.page--;
												}

												self.loadElements(); // Перезагружаем список
												BX.UI.Notification.Center.notify({
													content: HLCT.deleteSuccess,
													autoHideDelay: 2000
												});
											} else {
												BX.UI.Notification.Center.notify({
													content: HLCT.deleteError,
													autoHideDelay: 2000
												});
											}
											self.closeWaitLoader();
										},
										onfailure: function (err) {
											console.error('Ошибка удаления:', err);
											this.error = err.message || 'Ошибка при удалении элемента';
											self.closeWaitLoader();
										}
									});


								} catch (err) {
									console.error('Ошибка удаления:', err);
									this.error = err.message || 'Ошибка при удалении элемента';
									self.closeWaitLoader();
								}
							},
							// Переключение страницы
							onClickFirstPage() {
								this.pagination.page = 1;
								this.loadElements();
							},
							onClickPreviousPage() {
								this.pagination.page--;
								if (this.pagination.page <= 0) {
									this.pagination.page = 1;
								}
								this.loadElements();
							}
						},
						// language=Vue
						template:
								`
                                  <div class="vue-elements-list">
                                    <div v-if="error" class="ui-alert ui-alert-danger">
                                      <span class="ui-alert-message">{{ error }}</span>
                                    </div>
                                    <div class="vue-elements-list__controls">
                                      <button type="button" class="ui-btn ui-btn-primary ui-btn-xs"
                                              @click="loadElements"
                                              :disabled="isLoading">
                                        <span class="ui-icon ui-icon-refresh"></span>
                                        {{ lang.refresh }}
                                      </button>
                                      <button type="button" class="ui-btn ui-btn-link ui-btn-xs ui-btn-icon-add"
                                              @click="addElement"
                                              :disabled="isLoading">
                                        {{ lang.addElement }}
                                      </button>
                                    </div>

                                    <div class="vue-elements-list__table-wrapper">
                                      <table class="vue-elements-list__table">
                                        <thead>
                                        <tr>
                                          <th class="col-checkbox">
                                            <input type="checkbox"
                                                   :checked="allSelected"
                                                   :indeterminate.prop="someSelected"
                                                   @change="toggleSelectAll"
                                                   :disabled="isLoading || elements.length === 0">
                                          </th>
                                          <th v-if="headers.length" class="col-actions"></th>
                                          <th v-for="header in headers" :key="header.id">{{ header.content }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <template v-if="isLoading">
                                          <tr v-for="n in skeletonRowCount" :key="'sk-' + n" class="skeleton-row">
                                            <td v-for="c in skeletonColCount" :key="'sc-' + c">
                                              <div class="skeleton-cell"></div>
                                            </td>
                                          </tr>
                                        </template>
                                        <template v-else>
                                          <tr v-if="elements.length === 0">
                                            <td :colspan="headers.length + 2" class="vue-elements-list__empty">
                                              {{ lang.empty }}
                                            </td>
                                          </tr>
                                          <tr v-else v-for="element in elements" :key="element.ID"
                                              :class="{ 'row-selected': selectedIds.includes(element.ID) }">
                                            <td class="col-checkbox">
                                              <input type="checkbox" :value="element.ID" v-model="selectedIds">
                                            </td>
                                            <td class="col-actions">
                                              <a href="#" class="main-grid-row-action-button" @click.prevent="showRowMenu($event, element)"></a>
                                            </td>
                                            <td v-for="header in headers" :key="header.id">
                                              <span v-if="header.userTypeId === 'boolean'"
                                                    :class="['status-badge', element[header.id] === '1' ? 'status-active' : 'status-inactive']">{{ element[header.id] === '1' ? lang.statusActive : lang.statusInactive }}</span>
                                              <span v-else :class="{ 'table-data_value': header.id === 'UF_VALUE' }">{{ element[header.id] }}</span>
                                            </td>
                                          </tr>
                                        </template>
                                        </tbody>
                                      </table>
                                    </div>

                                    <div v-if="selectedIds.length > 0" class="bulk-actions-bar">
                                      <span class="bulk-actions-bar__count">{{ lang.bulkSelected }}: {{ selectedIds.length }}</span>
                                      <button type="button" class="ui-btn ui-btn-xs ui-btn-success-light" @click="bulkAction('activate')">{{ lang.bulkActivate }}</button>
                                      <button type="button" class="ui-btn ui-btn-xs ui-btn-warning-light" @click="bulkAction('deactivate')">{{ lang.bulkDeactivate }}</button>
                                      <button type="button" class="ui-btn ui-btn-xs ui-btn-danger-light" @click="bulkAction('delete')">{{ lang.bulkDelete }}</button>
                                      <button type="button" class="ui-btn ui-btn-xs ui-btn-light-border" @click="selectedIds = []">{{ lang.bulkDeselect }}</button>
                                    </div>

                                    <!-- Панель управления -->
                                    <div class="controls-panel">
                                      <div class="page-size-selector">
                                        <label for="pageSize">{{ lang.pageSize }}</label>
                                        <select
                                            id="pageSize"
                                            v-model="pagination.pageSize"
                                            @change="onPageSizeChange"
                                            :disabled="isLoading"
                                            class="page-size-select"
                                        >
                                          <option value="1">1</option>
                                          <option value="10">10</option>
                                          <option value="20">20</option>
                                          <option value="50">50</option>
                                          <option value="100">100</option>
                                        </select>
                                      </div>

                                      <div class="pagination-info" v-if="!isLoading && pagination.total > 0">
                                        {{ lang.showing }} {{ showingStart }}-{{ showingEnd }} {{ lang.of }} {{ pagination.total }} {{ lang.items }}
                                      </div>
                                    </div>

                                    <!-- Компонент пагинации -->
                                    <div class="pagination-wrapper" v-if="shouldShowPagination">
                                      <div class="pagination">
                                        <button
                                            @click="changePage(1)"
                                            :disabled="pagination.page === 1 || isLoading"
                                            class="pagination-btn pagination-first"
                                            :title="lang.firstPage"
                                        >
                                          «
                                        </button>

                                        <button
                                            @click="changePage(pagination.page - 1)"
                                            :disabled="pagination.page === 1 || isLoading"
                                            class="pagination-btn pagination-prev"
                                        >
                                          {{ lang.prev }}
                                        </button>

                                        <div class="pagination-pages">
                                          <button
                                              v-for="page in visiblePages"
                                              :key="page"
                                              @click="changePage(page)"
                                              :class="[
							'pagination-btn',
							'pagination-page',
							{
								active: page === pagination.page,
								disabled: page === '...'
							}
						]"
                                              :disabled="page === '...' || isLoading"
                                          >
                                            {{ page }}
                                          </button>
                                        </div>

                                        <button
                                            @click="changePage(pagination.page + 1)"
                                            :disabled="pagination.page >= totalPages || isLoading"
                                            class="pagination-btn pagination-next"
                                        >
                                          {{ lang.next }}
                                        </button>

                                        <button
                                            @click="changePage(totalPages)"
                                            :disabled="pagination.page === totalPages || isLoading"
                                            class="pagination-btn pagination-last"
                                            :title="lang.lastPage"
                                        >
                                          »
                                        </button>
                                      </div>

                                      <!-- Быстрая навигация -->
                                      <div class="pagination-quick-nav" v-if="totalPages > 10">
                                        <span>{{ lang.goTo }}</span>
                                        <input
                                            type="number"
                                            v-model.number="quickJumpPage"
                                            @keyup.enter="quickJumpToPage"
                                            :min="1"
                                            :max="totalPages"
                                            class="quick-jump-input"
                                            :disabled="isLoading"
                                        >
                                        <button
                                            @click="quickJumpToPage"
                                            :disabled="isLoading"
                                            class="quick-jump-btn"
                                        >
                                          OK
                                        </button>
                                      </div>
                                    </div>


                                  </div>
								`
					});

					app.mount('#vue-elements-list-app');
				});
			</script>

			<style>
				.pagination {
					list-style-type: none;
				}

				.table-data_value {
					max-height: 150px;
					overflow: overlay;
					display: block
				}

				.pagination-item {
					display: inline-block;
				}

				.pagination-item__active {
					background-color: #4AAE9B;
					color: #ffffff;
				}

				.vue-elements-list {
					margin: 0;
				}

				.vue-elements-list__panel {
					background: white;
					border-radius: 4px;
					box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
				}

				.vue-elements-list__alert {
					margin: 15px;
				}

				.vue-elements-list__controls {
					padding: 15px;
					border-bottom: 1px solid #e0e0e0;
					display: flex;
					flex-direction: row;
					flex-wrap: nowrap;
					justify-content: space-between;
				}

				.vue-elements-list__table-wrapper {
					overflow-x: auto;
				}

				.vue-elements-list__table {
					width: 100%;
					border-collapse: collapse;
				}

				.vue-elements-list__table th {
					background: #f8f9fa;
					padding: 12px;
					text-align: left;
					font-weight: 600;
					border-bottom: 1px solid #e0e0e0;
				}

				.vue-elements-list__table td {
					padding: 12px;
					border-bottom: 1px solid #e0e0e0;
				}

				.vue-elements-list__loading,
				.vue-elements-list__empty {
					text-align: center;
					padding: 40px !important;
					color: #6c757d;
				}

				@keyframes skeleton-shimmer {
					0%   { background-position: -400px 0; }
					100% { background-position: 400px 0; }
				}

				.skeleton-cell {
					height: 14px;
					border-radius: 4px;
					background: linear-gradient(90deg, #f0f0f0 25%, #e4e4e4 50%, #f0f0f0 75%);
					background-size: 400px 100%;
					animation: skeleton-shimmer 1.4s infinite linear;
				}

				.skeleton-row td {
					padding: 14px 12px;
				}

				.bulk-actions-bar {
					display: flex;
					align-items: center;
					gap: 8px;
					padding: 8px 15px;
					background: #eef5ff;
					border-bottom: 1px solid #c8dbf5;
				}

				.bulk-actions-bar__count {
					font-size: 13px;
					color: #555;
					margin-right: 4px;
				}

				.col-checkbox {
					width: 36px;
					text-align: center;
					padding: 8px !important;
				}

				.col-actions {
					width: 40px;
					text-align: center;
				}

				.row-selected td {
					background: #f0f6ff;
				}

				.main-grid-row-action-button {
					display: inline-block;
					width: 24px;
					height: 24px;
					background: url('/bitrix/images/1.gif') no-repeat center center;
					background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Ccircle cx='8' cy='3' r='1.5' fill='%23828d9a'/%3E%3Ccircle cx='8' cy='8' r='1.5' fill='%23828d9a'/%3E%3Ccircle cx='8' cy='13' r='1.5' fill='%23828d9a'/%3E%3C/svg%3E");
					background-repeat: no-repeat;
					background-position: center;
					border-radius: 4px;
					opacity: 0.6;
					transition: opacity 0.15s, background-color 0.15s;
				}

				.main-grid-row-action-button:hover {
					opacity: 1;
					background-color: #e5e8eb;
				}

				.status-badge {
					padding: 4px 8px;
					border-radius: 12px;
					font-size: 12px;
					font-weight: 500;
				}

				.status-active {
					background: #d4edda;
					color: #155724;
				}

				.status-inactive {
					background: #f8d7da;
					color: #721c24;
				}

				.vue-elements-list__pagination {
					padding: 15px;
					border-top: 1px solid #e0e0e0;
				}

				.pagination-container {
					position: relative;
					min-height: 200px;
				}

				.controls-panel {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 20px;
					padding: 15px;
					background: #f8f9fa;
					border-radius: 8px;
					flex-wrap: wrap;
					gap: 15px;
				}

				.page-size-selector {
					display: flex;
					align-items: center;
					gap: 10px;
				}

				.page-size-select {
					padding: 8px 12px;
					border: 1px solid #ddd;
					border-radius: 4px;
					background: white;
					cursor: pointer;
				}

				.page-size-select:disabled {
					opacity: 0.6;
					cursor: not-allowed;
				}

				.loading-overlay {
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: rgba(255, 255, 255, 0.9);
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: center;
					z-index: 100;
					border-radius: 8px;
				}

				.loading-spinner {
					width: 40px;
					height: 40px;
					border: 4px solid #f3f3f3;
					border-top: 4px solid #0066cc;
					border-radius: 50%;
					animation: spin 1s linear infinite;
					margin-bottom: 10px;
				}

				.loading-text {
					color: #666;
					font-size: 14px;
				}

				@keyframes spin {
					0% {
						transform: rotate(0deg);
					}
					100% {
						transform: rotate(360deg);
					}
				}

				.content-area {
					transition: all 0.3s ease;
				}

				.loading-blur {
					filter: blur(2px);
					opacity: 0.7;
					pointer-events: none;
				}

				.data-table {
					width: 100%;
					border-collapse: collapse;
					background: white;
				}

				.data-table th,
				.data-table td {
					padding: 12px;
					border: 1px solid #e0e0e0;
					text-align: left;
				}

				.data-table th {
					background: #f5f5f5;
					font-weight: 600;
				}

				.no-data {
					text-align: center;
					padding: 40px;
					color: #666;
					font-size: 16px;
					background: white;
					border-radius: 8px;
				}

				.pagination-wrapper {
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 15px;
					margin-top: 30px;
					padding: 20px;
					background: #f8f9fa;
					border-radius: 8px;
				}

				.pagination {
					display: flex;
					align-items: center;
					gap: 5px;
					flex-wrap: wrap;
					justify-content: center;
				}

				.pagination-pages {
					display: flex;
					gap: 5px;
				}

				.pagination-btn {
					padding: 10px 15px;
					border: 1px solid #ddd;
					background: white;
					cursor: pointer;
					border-radius: 6px;
					transition: all 0.3s ease;
					font-size: 14px;
					min-width: 44px;
					display: flex;
					align-items: center;
					justify-content: center;
				}

				.pagination-btn:hover:not(:disabled):not(.disabled) {
					background: #e9ecef;
					border-color: #0066cc;
					transform: translateY(-1px);
					box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
				}

				.pagination-btn.active {
					background: #0066cc;
					color: white;
					border-color: #0066cc;
					font-weight: 600;
				}

				.pagination-btn:disabled {
					opacity: 0.5;
					cursor: not-allowed;
					transform: none;
					box-shadow: none;
				}

				.pagination-btn.disabled {
					opacity: 0.5;
					cursor: default;
				}

				.pagination-first,
				.pagination-last {
					font-weight: bold;
				}

				.pagination-info {
					color: #666;
					font-size: 14px;
				}

				.pagination-quick-nav {
					display: flex;
					align-items: center;
					gap: 8px;
					font-size: 14px;
				}

				.quick-jump-input {
					width: 60px;
					padding: 6px 8px;
					border: 1px solid #ddd;
					border-radius: 4px;
					text-align: center;
				}

				.quick-jump-btn {
					padding: 6px 12px;
					background: #0066cc;
					color: white;
					border: none;
					border-radius: 4px;
					cursor: pointer;
					transition: background 0.3s;
				}

				.quick-jump-btn:hover:not(:disabled) {
					background: #0052a3;
				}

				.quick-jump-btn:disabled {
					opacity: 0.6;
					cursor: not-allowed;
				}

				/* Адаптивность */
				@media (max-width: 768px) {
					.controls-panel {
						flex-direction: column;
						align-items: stretch;
					}

					.page-size-selector {
						justify-content: space-between;
					}

					.pagination {
						gap: 3px;
					}

					.pagination-btn {
						padding: 8px 12px;
						min-width: 40px;
						font-size: 13px;
					}

					.data-table {
						font-size: 14px;
					}

					.data-table th,
					.data-table td {
						padding: 8px;
					}
				}

				@media (max-width: 480px) {
					.pagination-pages {
						order: 3;
						width: 100%;
						justify-content: center;
						margin-top: 10px;
					}

					.pagination {
						flex-direction: column;
						gap: 10px;
					}

					.pagination-btn {
						width: 100%;
						max-width: 200px;
					}
				}
			</style>
			<?php
		}
	}
}

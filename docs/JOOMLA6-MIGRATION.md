# K2 2.28 → Joomla! 6.2 兼容改造说明

本文档记录把 JoomlaWorks K2（v2.28，官方仅支持 Joomla 3）改造成可在 **Joomla! 6.2.0 + PHP 8.3** 上运行的完整改动清单、安装方法与已知限制。

---

## 1. 总体策略

没有把 260 多个文件重写为 Joomla 4/5/6 的命名空间式 MVC 结构，而是采用**兼容垫片（compatibility shim）**方案：

- Joomla 6 仍然支持「传统组件」调度：`bootComponent()` 找不到 `services/provider.php` 时会回退到 `LegacyComponent` + `LegacyComponentDispatcher`，直接 `include` `components/com_k2/k2.php`；
- 仍然支持传统路由文件 `router.php`（`k2BuildRoute()` / `k2ParseRoute()`）通过 `RouterLegacy` 调用；
- 仍然保留 `JLoader`、`jimport()`、`Table::getInstance()`、`Table::addIncludePath()`、`HTMLHelper::register()`；
- 插件仍可用「老式」多参数事件方法（`registerListeners()` 会把事件参数展开）。

因此只需补齐 Joomla 6 删掉的 `J*` 类与少量方法，就能用最小改动让 K2 跑起来，同时保留 K2 原有的全部业务逻辑与模板生态。

> 说明：Joomla 6 默认**不加载** `behaviour/compat` 的类别名表（`behaviour/compat6` 中 `classes_aliases` 默认关闭），所以 `JFactory`、`JText`、`JRequest` 等名字在干净的 J6 站点上是**未定义**的 —— 这正是 K2 直接在 J6 上报「Class not found」的根本原因。

---

## 2. 新增文件

| 文件 | 作用 |
|---|---|
| `administrator/components/com_k2/helpers/compat.php` | **核心兼容层**（约 1000 行）。重建 K2 需要的全部 `J*` 类：能映射的用 `class_alias()` 指向 CMS 真实类；已被删除的（`JRequest`、`JError`、`JResponse`、`JDispatcher`、`JApplication`、`JSite`、`JUtility`、`JMailHelper`、`JArchive`、`JPane`、`JMenu`、`JSubMenuHelper`、`JFeedItem`…）用最小实现补齐。同时注册 `HTMLHelper::register()` 兜底 `behavior.framework`、`jquery.framework`、`behavior.modal`、`formbehavior.chosen`、`tabs.start/panel/end` 等 J6 已移除的 HTML 助手。 |
| `media/k2/assets/js/k2.modal.js` | **模态框桥接**。Joomla 6 删除了 `media/system/js/squeezebox.js`，而 K2 后台的图片/视频/附件选择器仍调用 `SqueezeBox.initialize()` / `fromElement()` / `close()`。该文件提供同签名、零依赖的替代实现，并额外暴露 `K2Modal`。 |

兼容层带 `K2_J6_COMPAT_LOADED` 幂等保护，即使「Behaviour - Backward Compatibility」插件被启用也不会冲突。

---

## 3. 改写的基础类

| 文件 | 改动 |
|---|---|
| `components/com_k2/controllers/controller.php`（前台）<br>`administrator/components/com_k2/controllers/controller.php`（后台） | `K2Controller extends \Joomla\CMS\MVC\Controller\BaseController`，并重写 `display()` / `getView()` / `getModel()`，保持 Joomla 3 的 `getView() → setModel($model, true) → display()` 语义，绕开 J4+ MVC 工厂的命名约定。 |
| `administrator/components/com_k2/models/model.php` | `K2Model extends \Joomla\CMS\MVC\Model\BaseDatabaseModel`，自行实现 `getInstance()` / `addIncludePath()`（J4 起 `JModelLegacy` 的这两个静态方法已删除），用 `JLoader::register()` 把 `models/*.php` 映射成 `K2Model{Name}`，并同步写入父类的 include path 表，兼容传统工厂。 |
| `administrator/components/com_k2/views/view.php` | `K2View extends \Joomla\CMS\MVC\View\HtmlView`，加 `#[\AllowDynamicProperties]`（K2 视图大量动态属性）；YOOtheme PRO 集成改为 J4+ 的 `$dispatcher->dispatch('onLoadTemplate', new \Joomla\Event\Event(...))`，并用 try/catch 包裹。 |

---

## 4. 入口文件注入

以下 14 个入口在 `defined('_JEXEC') or die;` 之后统一注入兼容层：

```
components/com_k2/k2.php
components/com_k2/router.php
administrator/components/com_k2/k2.php
administrator/components/com_k2/elements/base.php
administrator/modules/mod_k2_quickicons/mod_k2_quickicons.php
administrator/modules/mod_k2_stats/mod_k2_stats.php
modules/mod_k2_{comments,content,tools,user,users}/*.php
plugins/system/k2.php
plugins/search/k2.php
plugins/user/k2.php
plugins/finder/k2.php
script.k2.php（条件注入）
```

组件入口额外补注册 `K2HelperHTML` / `K2HelperUtilities` 与 `K2Model::addIncludePath()`，这样即使「System - K2」插件被停用，组件也不会因为类找不到而白屏。

`compat.php` 同时为 `K2_JVERSION`、`K2_CURRENT_VERSION` 提供兜底定义（系统插件里也改成了 `if (!defined())` 的写法，避免常量重复定义警告）。

---

## 5. 被替换的已删除 API

批量替换（共 200+ 处，全部已通过 `php -l`）：

| Joomla 3 写法 | Joomla 6 写法 | 数量 |
|---|---|---|
| `$app->isSite()` | `$app->isClient('site')` | 56 |
| `$app->isAdmin()` | `$app->isClient('administrator')` | 26 |
| `$app->getCfg('x')` | `$app->get('x')` | 62 |
| `$config->getValue('config.x')` | `$config->get('x')` | 13 |
| `$app->close()` | `K2Compat::close()` | 36 |
| `$db->query()` | `$db->execute()` | 111 |
| `$db->getEscaped()` | `$db->escape()` | 10 |
| `$db->insertObject(...)` | `K2Compat::insertObject($db, ...)` | 4 |
| `$db->getErrorMsg()` | `K2Compat::dbError($db)` | 11 |
| `$db->getErrorNum()` | `K2Compat::dbErrno($db)` | 10 |
| `JPlugin::loadLanguage(...)` | `K2Compat::loadLanguage(...)`（J4 起该方法变成 protected 实例方法） | 6 |
| `is_a($sql, 'JDatabaseQuery')` | `is_a($sql, \Joomla\Database\QueryInterface::class)` | 1 |

`getErrorMsg()` / `getErrorNum()` 在 Joomla 4 已被删除，`K2Compat` 直接从底层连接（`mysqli` / `PDO`）读取错误信息，K2 只把它们用于报错提示，不参与流程判断。

`K2_JVERSION == '15'` 分支里的 `loadResultArray()`、`getTableList()` 等不会被求值（三元表达式只计算命中的分支），保持原样。

---

## 6. 脚本加载顺序（关键修复）

Joomla 4+ 通过 WebAssetManager 加载 jQuery 时带 `defer`，而 K2 的脚本是用 `JDocument::addScript()` 输出的**同步** `<script>`。按 `ScriptsRenderer` 的渲染规则（WA 资源在前、传统脚本在后），同步脚本会在**解析阶段**执行，早于 defer 的 jQuery，结果是 `jQuery is not defined`，整个后台 JS 全部失效。

处理方式：

1. `K2Compat::loadJquery()` 改为**同步**引入 `media/vendor/jquery/js/jquery.min.js`（`addScript()` 天然按 src 去重）；本地文件缺失时才回退到 `useScript('jquery')`。
2. `k2.backend.js` 顶部 `var $K2 = jQuery.noConflict();` 之后补 `var $ = $K2;` —— J4+ 后台没有全局 `$`，而该文件仍有 70 处裸 `$()` 调用（其中一个顶层 `$(document).ready()` 会直接抛错并中断后续所有初始化）。
3. jQuery UI 由 1.8.24 统一升级为 **1.12.1**（1.8.x 与 jQuery 3 不兼容，`tabs()` / `sortable()` 会报错）；触屏补丁与 smoothness 主题只在媒体管理器载入。
4. `k2.modal.js` 在 `k2.backend.js` 之前入队。
5. `JHtml::_('behavior.framework')` 的垫片除了加载 jQuery，还会 `useScript('core')` + `useScript('tableordering')` —— Joomla 4 起该助手还负责加载核心 JS，K2 列表页的列排序（`Joomla.tableOrdering()`）与工具栏提交（`Joomla.submitbutton()`）都依赖它。

---

## 7. 安装脚本与清单

- `k2.xml`：删除 `<installfile>` / `<uninstallfile>`（Joomla 4+ 已不支持），保留 `<scriptfile>script.k2.php</scriptfile>`、7 个模块、4 个插件的声明；
- `manifest.xml` 与 `k2.xml` 内容保持一致（部分安装路径会找 `manifest.xml`）；
- 11 个模块/插件清单改用现代的 `<extension type="module|plugin" method="upgrade">` 结构；
- `script.k2.php`：
  - `$parent->getParent()->manifest` → `getManifest()`；
  - `new JInstaller` → `new \Joomla\CMS\Installer\Installer()`；
  - 移除 J3 → J2.5 的文件删除/改名逻辑；
  - 模块/插件的子安装包用 `try/catch` 包裹，单个子扩展失败不再中断整个组件安装；
  - 末尾追加 `class_alias('Com_K2InstallerScript', 'k2InstallerScript')` —— J6 的脚本类名由 `element + 'InstallerScript'` 推导（`com_k2InstallerScript`，PHP 类名大小写不敏感故可命中），再补一个 `k2` 的别名最稳妥。
- 删除 Joomla 1.5 时代的 `install.k2.php` / `uninstall.k2.php`（清单早已不引用）。

安装用 SQL（`administrator/components/com_k2/install.mysql.sql`）本身已经是 `utf8mb4`；其中的 `DEFAULT '0000-00-00 00:00:00'` 依赖会话 `sql_mode`，Joomla 的 MySQLi 驱动默认设置 `STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`（**不含** `NO_ZERO_DATE`），所以在 MySQL 8 上可以正常建表。

---

## 8. 插件

| 插件 | 处理 |
|---|---|
| `system/k2` | 兼容层注入；常量定义改为 `if (!defined())`；`$app->getCfg()`、`JResponse`、`JRequest` 由兼容层接管 |
| `finder/k2` | `FinderIndexer*` 改为 `Joomla\Component\Finder\Administrator\Indexer\{Adapter,Helper,Indexer,Result}`；`castAsChar()` → `castAs('CHAR', …)`；`$this->indexer->index($item)`；`JEventDispatcher::getInstance()->trigger()` → `$app->triggerEvent()`；`JDatabaseQuery` 判断改用 `QueryInterface` |
| `search/k2`、`user/k2` | 保留 J3 的多参数事件方法（`registerListeners()` 会展开参数），仅做兼容层注入与 API 替换 |

`finder/k2` 还补了 `getSubscribedEvents()`：Joomla 5+ 通过订阅器分发事件，而核心 `FinderIndexerAdapter` 只订阅了自己的 4 个索引事件，K2 的 `onFinderAfterSave` / `onFinderAfterDelete` / `onFinderBeforeSave` / `onFinderChangeState` 必须显式并入，否则文章保存后不会重建索引。

---

## 9. 按调用点修复的细节（容易漏、但是致命）

| 问题 | Joomla 6 现状 | 处理 |
|---|---|---|
| `JFilterOutput::cleanText()` / `objectHTMLSafe()` / `ampReplace()` / `stringURLUnicodeSlug()` | J4 起 `Joomla\CMS\Filter\OutputFilter` 只剩 `stringURLSafe()`、`linkXHTMLSafe()`、`stringJSSafe()` | **不能**用 `class_alias`，改为真实垫片类 `JFilterOutput`，按 J3 语义重写这 4 个方法（19 处调用） |
| `JFactory::getEditor()` + `$editor->save()` | `JEditor` 整体移除；`Editor` 只剩 `display()` / `getButtons()`，`save()` / `getContent()` / `setContent()` 没了 | 新增 `K2Editor` 适配器，`display()` 透传（J6 的签名仍未加类型声明，`'100%'`、按钮数组都能用），`save()` 返回 `Joomla.editors.instances[...]` 的同步 JS；5 处 `JFactory::getEditor()` 改为 `K2Compat::getEditor()` |
| `JFactory::getURI()` | 已移除 | 11 处改为 `Joomla\CMS\Uri\Uri::getInstance()` |
| `JHtml::_('behavior.mootools')` | `Behavior` 类没有 `mootools()`，会抛 `InvalidArgumentException` | 注册同名兜底 → 只加载 jQuery |
| `JHtml::_('script', 'openid.js')` | `'script'` 服务键不存在 | 注册兜底 → `JDocument::addScript()` |
| `JREQUEST_ALLOWRAW` | 常量被删除，PHP 8 下是致命错误 | `compat.php` 恢复 `JREQUEST_NOTRIM` / `JREQUEST_ALLOWRAW` / `JREQUEST_ALLOWHTML` |
| `JROUTER_MODE_SEF`、`$router->getMode()` | 常量与 `Router::getMode()` 都已移除 | 常量兜底；`modules/mod_k2_user/helper.php` 里直接按 SEF 处理（J4+ 恒为 SEF） |
| `jexit()` | 仍在，但怕环境裁剪 | `function_exists` 兜底 |
| **安装期 SQL 与 MySQL 8 严格模式** | `script.k2.php` 插默认用户组用 `VALUES('', ...)`，自增 `id` 列不接受空字符串，报 `Incorrect integer value: '' for column 'id'`，整个安装回滚（前台随之 404） | 4 处改为不写 `id` 列：`INSERT INTO #__k2_user_groups (\`name\`, \`permissions\`) VALUES(...)`；`models/users.php` 里 `#__k2_users` 的 `VALUES ('', ...)` 改为 `VALUES (NULL, ...)` |
| **建表 SQL 零日期默认值** | `install.mysql.sql` 有 7 处 `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'`，MySQL 8 严格模式（含 NO_ZERO_DATE）下 `CREATE TABLE` 直接失败 | 全部改为 `datetime NULL DEFAULT NULL`（Joomla 4+ 核心同样处理） |
| 自定义字段类型 `k2category` / `k2tags` / `k2users` / `k2modalselector` | 新版只对声明了 `addfieldpath` 的表单实例生效 | `compat.php` 里额外 `Form::addFieldPath(<admin>/components/com_k2/elements)` 全局注册 |
| `K2Parameter` 动态属性 `$values` | PHP 8.2+ 弃用告警 | 显式声明属性 |
| **`Class "K2HelperUtilities" not found`（全新安装后后台即报）** | 全新安装的插件默认**禁用**，「System - K2」没启用时 `onAfterInitialise` 不执行，原本由它注册的 `K2HelperUtilities` / `K2HelperRoute` / `K2HelperPermissions` / `K2GlobalHelper` / `K2Model` / `K2View` / `K2Controller` 全部失联；后台首页的 mod_k2_stats / mod_k2_quickicons 一渲染就崩 | compat.php（已被全部 11 个入口注入）新增注册兜底区：用 `JLoader::register()` 注册上述全部类，`K2Controller` 按 `JPATH_BASE` 区分前后台，与原系统插件语义一致 |
| **`Call to undefined method K2View*::assignRef()`（后台所有页面）** | `JViewLegacy::assignRef()` / `assign()` 在 J4 被删除，K2 视图有 183 处调用 | 前后台共用的基类 `administrator/components/com_k2/views/view.php`（`K2View extends HtmlView`，22 个视图全部继承它）补回 `assignRef()`（保留引用绑定语义）与 `assign()`；J6 的 `HtmlView` 仍保留 `_addPath` / `addHelperPath` / `addTemplatePath` / `loadHelper` / `setLayout`，无需处理 |
| **`Fields::onContentPrepare(): Argument #1 must be of type ContentPrepareEvent, Joomla\Event\Event given`（文章编辑页）** | J4+ 核心插件用**类型化事件签名**（`ContentPrepareEvent` 等），K2 的 `trigger()` 垫片派发通用 `Event` → TypeError；且 J6 dispatcher 不收集监听器返回值，K2 前台插件输出（onContentAfterTitle 等）会全丢 | `K2Compat::createEvent()` 内置 17 个核心事件名 → 具体事件类 + 参数键名映射（Content / Model / User / Finder 四组），按 J3 位置参数组合命名参数构造类型化事件；`JDispatcher::trigger()` 改为 `getListeners()` 后直接遍历调用并收集全部非空返回值（顺序与 dispatch 一致） |
| **后台列表页工具栏按钮（New / Publish 等）点击无反应** | J6 的 `Joomla.submitbutton(task)` 先找 `form.form-validate` 表单，再调 `document.formvalidator.isValid()`——K2 页面没加载校验脚本（`document.formvalidator` 不存在）→ 点击抛 TypeError；且 K2 模板大量使用 J3 全局函数 `submitbutton()` / `submitform()`，J4+ 已无全局函数 | `k2.backend.js` 开头在 DOMContentLoaded（core.js 是 defer，此时已执行完）后打补丁：包装官方 `Joomla.submitbutton`，`formvalidator` 缺失时强制 `validate=false` 直接提交；补齐 `window.submitform` / `window.submitbutton` 全局函数（内部转发 Joomla 版本）；core.js 整个缺失时自带最小实现 |

**MVC 链路**（最关键的一环）：K2 的类名是 `K2ControllerItems` / `K2ViewItems` / `K2ModelItems`，不符合 Joomla 4 MVC 工厂的命名约定。现在的处理：

- `K2Controller`（前后台各一份）重写 `display()`，按 Joomla 3 的语义显式 `getView()` → `setModel($model, true)` → `$view->display()`，不再依赖 `BaseController::prepareViewModel()`；
- `getView()` 先走核心解析，失败则回退到 `{base}/views/{name}/view.{type}.php` + `K2View{Name}`；
- `getModel()` 先走 `K2Model::getInstance($name, 'K2Model')`，找不到再回退父类；
- `K2Model::addIncludePath()` 除了给 `JLoader` 注册类，还会把目录塞进 `BaseDatabaseModel` 的 include path 表，保证传统工厂 `BaseDatabaseModel::getInstance()` 也能找到模型文件。

---

## 10. 安装步骤

1. 备份数据库与站点文件（**必做**）。
2. 系统 → 扩展管理 → 安装扩展 → 上传 `k2-2.28-joomla6.zip`。
3. 安装完成后确认：
   - 「System - K2」插件已启用（K2 的路由、常量、类加载都依赖它）；
   - 「Search - K2」「User - K2」按需启用；「Finder - K2」需要在智能搜索里建立索引；
   - 后台 K2 菜单能打开「文章 / 分类 / 标签 / 评论 / 用户 / 额外字段 / 媒体管理器 / 信息」。
4. 打开 K2 参数页切换一次并保存（会补齐新版本的默认参数）。
5. 前台访问一个 K2 分类页、一个 K2 文章页，确认路由正常。

> 从旧版 K2 升级时：先禁用「System - K2」，再上传覆盖安装，最后重新启用，可避免旧文件被 PHP 缓存。

---

## 11. 已知限制

1. **jQuery UI / flatpickr / Magnific Popup / Fancybox 走 CDN**（沿用 K2 原有做法）。后台需要能访问 `cdnjs.cloudflare.com`；内网环境请把这些文件下载到 `media/k2/assets/vendors/` 并改写 `administrator/components/com_k2/helpers/html.php` 里的地址。
2. **后台样式是「能用」而非「原生 Atum 风格」**。Bootstrap 5 与 K2 的 Bootstrap 2 标记有差异，已在 `k2.backend.css` 末尾追加一节 «Joomla! 4/5/6 compatibility layer» 做基础归一化（标签页、隐藏类、消息容器）。若有细节错位，属样式问题，不影响数据。
3. **前台模板覆盖**：`templates/<模板>/html/com_k2/` 下的老覆盖文件仍按 K2 老结构渲染；如遇 J6 报错，先临时改名为 `.bak` 让 K2 使用自带模板，再逐个适配。
4. **HTTP 缓存头**：`plugins/system/k2.php` 的 `onAfterRender` 通过 `JResponse::setBody()` 改写的逻辑在 J4+ 上语义已变，目前只保证「读取页面内容做正则匹配」可用，缓存头输出可能不生效（不影响功能）。
5. `$user->gid` 是 K2 的历史遗留动态属性，PHP 8.2+ 会报 deprecated。已在赋值处用 `@` 抑制，读取照常。
6. Joomla 7 将彻底移除 `Table::getInstance()`、`BaseController` 的传统工厂兼容层与老式插件监听器 —— 本方案是**面向 Joomla 6.x 的过渡方案**，未来升级到 J7 需要做真正的 MVC 重写。

---

## 12. 回滚

保留原版 `k2-master` 目录即可：把 `compat.php`、`k2.modal.js` 删除，并用原版文件覆盖被改写的 5 个基础文件与 14 个入口文件，即回到 K2 2.28 原状（此时仅支持 Joomla 3）。

---

## 13. 迁移修复记录（按轮次追加）

### 第 1 轮：安装与类加载
- MySQL 8 严格模式拒绝 `VALUES('',…)`（自增 id）与 `0000-00-00` 默认日期：
  - `script.k2.php` 4 处默认用户组 INSERT 去掉 id 列；
  - `install.mysql.sql` 7 处 `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'` 改为 `datetime NULL DEFAULT NULL`；
  - `models/users.php` 运行时 INSERT 同样去掉 id 列。
- 插件默认禁用导致 `K2HelperUtilities not found`：compat.php 增加兜底类注册块（K2HelperHTML/Utilities/Route/Permissions、K2GlobalHelper、K2String、K2Controller/K2Model/K2View 按 JPATH_BASE 注册）。

### 第 2 轮：MVC 基类
- 全部后台页面报 `Call to undefined method K2View*::assignRef()`：共享视图基类 `views/view.php` 补回 `assignRef()`/`assign()`（J4+ 已移除），全项目 183 处调用。
- 控制器工厂命名不匹配（K2ControllerItems vs J4 约定）：K2Controller 基类重写 `display()/getView()/getModel()` 恢复 J3 语义；K2Model 补 `getInstance()/addIncludePath()`。

### 第 3 轮：类型化事件
- `Fields::onContentPrepare(): Argument #1 must be of type ContentPrepareEvent, Joomla\Event\Event given`：compat.php 新增 `K2Compat::createEvent()`，把 17 个核心事件名映射到 J6 具体事件类（Content/Model/User/Finder）；`JDispatcher::trigger()` 改为遍历 `getListeners()` 手动收集返回值（J6 dispatch 不收集返回值，K2 前台插件输出依赖它）。

### 第 4 轮：JS 提交按钮
- 分类/标签页 New 按钮无反应：J6 core.js 的 `Joomla.submitbutton` 依赖 `document.formvalidator`，而 K2 模板调用已移除的 `submitform()/submitbutton()` 全局。`k2.backend.js` 在 DOMContentLoaded 时包装 `Joomla.submitbutton`（无 formvalidator 则跳过验证直接提交）并补回两个全局函数；`k2.modal.js` 提供 SqueezeBox 兼容（Bootstrap 5 modal）。

### 第 5 轮：事件参数数量不匹配
- 后台文章 New 报 `array_combine(): Argument #1 ($keys) and argument #2 ($values) must have the same number of elements`：
  - 根因：K2 大量以 **4 个**位置参数触发 `onContentPrepare`（context, subject, params, limitstart），而映射表只有 3 个命名键；`onUserAfterDelete` 甚至 3 参对 1 键。PHP 8 中 `array_combine()` 数量不匹配直接抛 ValueError（不再返回 false）。
  - 修复：`K2Compat::createEvent()` 改为按键名逐位映射，**多余的位置参数静默丢弃**（与 Joomla 核心自身传参一致）；缺少必需命名参数时构造器抛异常 → 回退通用 Event。

### 第 6 轮：后台文字白字白底不可见
- 现象：后台各 K2 菜单页打开正常，但很多文字与背景都是白色，文字看不见。
- 根因：Joomla 5+ 的 Atum 后台模板支持深色配色（`data-bs-theme="dark"`，模板参数默认「跟随操作系统」），Bootstrap 深色模式会把 `--bs-body-color` 等变量翻转为浅色文字；而 K2 的 CSS 强制自身容器白色背景 → 浅色文字落在白底上不可见。
- 修复：`media/k2/assets/css/k2.backend.css` 兼容层新增「浅色孤岛」规则——在 `#k2AdminContainer / #k2MoveDialogContainer / #k2ModalContainer` 上重置 Bootstrap 的 `--bs-*` 颜色变量（body/bg/border/link/table 全套）、强制 `color-scheme: light`（原生表单控件也渲染为浅色）、标题固定为 K2 深蓝 `#183867`、form-control 白底深字。无论 Atum 处于浅色还是深色模式，K2 界面都保持白底深字。
- 备注：浏览器可能缓存旧 CSS，验证时需 Ctrl+F5 强刷；也可在「系统 → 站点模板样式 → Atum - 管理员 → Dark Mode」改为 Light 作为对照。

### 第 7 轮：后台界面观感调整（用户反馈）
1. **移除 K2 自带蓝色侧边栏**：`administrator/components/com_k2/k2.php` 不再输出 `#k2Sidebar` 与移动版菜单，避免与 Joomla 后台左侧菜单重复；容器 `#k2AdminContainer` 由 `grid 10%/90%` 改为 `display:block`，内容区保留 15px 左右内边距。
2. **列表复选框黑底问题**：Joomla 6 的 `JHtml::_('grid.checkedout')` 输出 `.form-check-input`，Bootstrap 5 深色模式下其 `--bs-form-check-bg` 解析为深色背景，看起来像"已选中"。兼容层新增 `:not(:checked)` 白底 + 灰边框规则，选中态保持蓝色勾选不变（覆盖 `#k2AdminContainer`/`#k2MoveDialogContainer`/`#k2ModalContainer`）。
3. **筛选栏不再固定**：`.k2AdminTableFilters` 由 `position:fixed;left:10%;width:90%` 改为 `position:static;width:100%`；同时移除 `k2.backend.js` 里按筛选栏高度给 `.k2AdminTableData` 补 `padding-top` 的逻辑（否则会留出大块空白），CSS 默认 padding 由 50px 改为 10px。
4. **Image 标签页文件框文字被切一半**：Atum 对 `input[type="file"]` 设了过紧的高度/行高，兼容层为 `.itemAdditionalData input[type="file"]` 恢复 `height:auto;min-height:34px;line-height:1.4`、白底、13px 字号。

### 第 8 轮：列表被挤成窄条 / 复选框仍黑（缓存问题）
- 现象：第 7 轮装完后，文章列表内容被挤到左侧约 10% 宽（~110px），右侧大面积空白，复选框仍为黑色填充。
- 根因：**浏览器仍在用旧的 `k2.backend.css`**。K2 的静态资源版本号是 `?v=K2_CURRENT_VERSION`（固定 2.28），多次重建包都不会改变，浏览器直接从缓存取旧文件；旧 CSS 里 `#k2AdminContainer` 仍是 `grid 10%/90%` 两列，而侧边栏 DOM 已被移除（PHP 是新的），唯一子元素 `#k2ContentView` 落进第一列 10%，于是内容被压成窄条。
- 修复：`K2HelperHTML` 新增 `assetVersion()`，静态资源 URL 改为 `?v=版本号.filemtime`，文件一更新 URL 即变化，彻底避开缓存。已应用于 `k2.backend.css`、`k2.global.css`、`k2.backend.js`、`k2.modal.js`。
- 验证方法：装包后打开列表页，查看页面源码里 CSS 链接是否带 `?v=2.28.17xxxxxxxx` 形式的时间戳；若有则说明已生效。另可在「系统 → 清除缓存」清一次 Joomla 缓存，浏览器端 Ctrl+F5 强刷。

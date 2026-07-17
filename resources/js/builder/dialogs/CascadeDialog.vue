<script setup lang="ts">
import type { CascadeLevel, CascadeNode } from '../types/schema';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

export interface CascadeDialogState {
  elementId: string;
  levels: CascadeLevel[];
  data: CascadeNode[];
}

const model = defineModel<CascadeDialogState | null>();
const store = useSurveyBuilderStore();

function addLevel() {
  if (!model.value || model.value.levels.length >= 5) return;
  model.value.levels.push({ id: `lvl_${Math.random().toString(36).slice(2, 9)}`, label: `層級 ${model.value.levels.length + 1}` });
}

function removeLevel(levelIndex: number) {
  if (!model.value || levelIndex < 0 || levelIndex >= model.value.levels.length) return;
  model.value.levels.splice(levelIndex, 1);
  model.value.data = model.value.levels.length === 0
    ? []
    : removeDepth(model.value.data, levelIndex);
}

function removeDepth(nodes: CascadeNode[], targetDepth: number, currentDepth = 0): CascadeNode[] {
  if (currentDepth === targetDepth) {
    return nodes.flatMap((node) => node.children ?? []);
  }

  return nodes.map((node) => ({
    ...node,
    children: node.children ? removeDepth(node.children, targetDepth, currentDepth + 1) : [],
  }));
}

function pruneDepth(nodes: CascadeNode[], maxDepth: number, currentDepth = 1): CascadeNode[] {
  return nodes.map((n) => {
    if (currentDepth >= maxDepth) return { id: n.id, label: n.label };
    return { ...n, children: n.children ? pruneDepth(n.children, maxDepth, currentDepth + 1) : [] };
  });
}

function maxDepth(nodes: CascadeNode[], d = 1): number {
  let max = d;
  for (const n of nodes) {
    if (n.children && n.children.length > 0) max = Math.max(max, maxDepth(n.children, d + 1));
  }
  return max;
}

function nd(label: string, children?: CascadeNode[]): CascadeNode {
  return { id: `nd_${Math.random().toString(36).slice(2, 9)}`, label, children: children ?? [] };
}

function buildTaiwanData(): CascadeNode[] {
  return [
    nd('基隆市', [nd('仁愛區'),nd('信義區'),nd('中正區'),nd('中山區'),nd('安樂區'),nd('暖暖區'),nd('七堵區')]),
    nd('臺北市', [nd('松山區'),nd('信義區'),nd('大安區'),nd('中山區'),nd('中正區'),nd('大同區'),nd('萬華區'),nd('文山區'),nd('南港區'),nd('內湖區'),nd('士林區'),nd('北投區')]),
    nd('新北市', [nd('板橋區'),nd('三重區'),nd('中和區'),nd('永和區'),nd('新莊區'),nd('新店區'),nd('樹林區'),nd('鶯歌區'),nd('三峽區'),nd('淡水區'),nd('汐止區'),nd('瑞芳區'),nd('土城區'),nd('蘆洲區'),nd('五股區'),nd('泰山區'),nd('林口區'),nd('深坑區'),nd('石碇區'),nd('坪林區'),nd('三芝區'),nd('石門區'),nd('八里區'),nd('平溪區'),nd('雙溪區'),nd('貢寮區'),nd('金山區'),nd('萬里區'),nd('烏來區')]),
    nd('桃園市', [nd('桃園區'),nd('中壢區'),nd('大溪區'),nd('楊梅區'),nd('蘆竹區'),nd('大園區'),nd('龜山區'),nd('八德區'),nd('龍潭區'),nd('平鎮區'),nd('新屋區'),nd('觀音區'),nd('復興區')]),
    nd('新竹市', [nd('東區'),nd('北區'),nd('香山區')]),
    nd('新竹縣', [nd('竹北市'),nd('湖口鄉'),nd('新豐鄉'),nd('新埔鎮'),nd('關西鎮'),nd('芎林鄉'),nd('寶山鄉'),nd('竹東鎮'),nd('五峰鄉'),nd('橫山鄉'),nd('尖石鄉'),nd('北埔鄉'),nd('峨眉鄉')]),
    nd('苗栗縣', [nd('竹南鎮'),nd('頭份市'),nd('三灣鄉'),nd('南庄鄉'),nd('獅潭鄉'),nd('後龍鎮'),nd('通霄鎮'),nd('苑裡鎮'),nd('苗栗市'),nd('造橋鄉'),nd('頭屋鄉'),nd('公館鄉'),nd('大湖鄉'),nd('泰安鄉'),nd('銅鑼鄉'),nd('三義鄉'),nd('西湖鄉'),nd('卓蘭鎮')]),
    nd('臺中市', [nd('中區'),nd('東區'),nd('南區'),nd('西區'),nd('北區'),nd('北屯區'),nd('西屯區'),nd('南屯區'),nd('太平區'),nd('大里區'),nd('霧峰區'),nd('烏日區'),nd('豐原區'),nd('后里區'),nd('石岡區'),nd('東勢區'),nd('和平區'),nd('新社區'),nd('潭子區'),nd('大雅區'),nd('神岡區'),nd('大肚區'),nd('沙鹿區'),nd('龍井區'),nd('梧棲區'),nd('清水區'),nd('大甲區'),nd('外埔區'),nd('大安區')]),
    nd('彰化縣', [nd('彰化市'),nd('芬園鄉'),nd('花壇鄉'),nd('秀水鄉'),nd('鹿港鎮'),nd('福興鄉'),nd('線西鄉'),nd('和美鎮'),nd('伸港鄉'),nd('員林市'),nd('社頭鄉'),nd('永靖鄉'),nd('埔心鄉'),nd('溪湖鎮'),nd('大村鄉'),nd('埔鹽鄉'),nd('田中鎮'),nd('北斗鎮'),nd('田尾鄉'),nd('埤頭鄉'),nd('溪州鄉'),nd('竹塘鄉'),nd('二林鎮'),nd('大城鄉'),nd('芳苑鄉'),nd('二水鄉')]),
    nd('南投縣', [nd('南投市'),nd('中寮鄉'),nd('草屯鎮'),nd('國姓鄉'),nd('埔里鎮'),nd('仁愛鄉'),nd('名間鄉'),nd('集集鎮'),nd('水里鄉'),nd('魚池鄉'),nd('信義鄉'),nd('竹山鎮'),nd('鹿谷鄉')]),
    nd('雲林縣', [nd('斗南鎮'),nd('大埤鄉'),nd('虎尾鎮'),nd('土庫鎮'),nd('褒忠鄉'),nd('東勢鄉'),nd('台西鄉'),nd('崙背鄉'),nd('麥寮鄉'),nd('斗六市'),nd('林內鄉'),nd('古坑鄉'),nd('莿桐鄉'),nd('西螺鎮'),nd('二崙鄉'),nd('北港鎮'),nd('水林鄉'),nd('口湖鄉'),nd('四湖鄉'),nd('元長鄉')]),
    nd('嘉義市', [nd('東區'),nd('西區')]),
    nd('嘉義縣', [nd('番路鄉'),nd('梅山鄉'),nd('竹崎鄉'),nd('阿里山鄉'),nd('中埔鄉'),nd('大埔鄉'),nd('水上鄉'),nd('鹿草鄉'),nd('太保市'),nd('朴子市'),nd('東石鄉'),nd('六腳鄉'),nd('新港鄉'),nd('民雄鄉'),nd('大林鎮'),nd('溪口鄉'),nd('義竹鄉'),nd('布袋鎮')]),
    nd('臺南市', [nd('中西區'),nd('東區'),nd('南區'),nd('北區'),nd('安平區'),nd('安南區'),nd('永康區'),nd('歸仁區'),nd('新化區'),nd('左鎮區'),nd('玉井區'),nd('楠西區'),nd('南化區'),nd('仁德區'),nd('關廟區'),nd('龍崎區'),nd('官田區'),nd('麻豆區'),nd('佳里區'),nd('西港區'),nd('七股區'),nd('將軍區'),nd('學甲區'),nd('北門區'),nd('新營區'),nd('後壁區'),nd('白河區'),nd('東山區'),nd('六甲區'),nd('下營區'),nd('柳營區'),nd('鹽水區'),nd('善化區'),nd('大內區'),nd('山上區'),nd('新市區'),nd('安定區')]),
    nd('高雄市', [nd('楠梓區'),nd('左營區'),nd('鼓山區'),nd('三民區'),nd('鹽埕區'),nd('前金區'),nd('新興區'),nd('苓雅區'),nd('前鎮區'),nd('旗津區'),nd('小港區'),nd('鳳山區'),nd('林園區'),nd('大寮區'),nd('大樹區'),nd('大社區'),nd('仁武區'),nd('鳥松區'),nd('岡山區'),nd('橋頭區'),nd('燕巢區'),nd('田寮區'),nd('阿蓮區'),nd('路竹區'),nd('湖內區'),nd('茄萣區'),nd('永安區'),nd('彌陀區'),nd('梓官區'),nd('旗山區'),nd('美濃區'),nd('六龜區'),nd('甲仙區'),nd('杉林區'),nd('內門區'),nd('茂林區'),nd('桃源區'),nd('那瑪夏區')]),
    nd('屏東縣', [nd('屏東市'),nd('三地門鄉'),nd('霧臺鄉'),nd('瑪家鄉'),nd('九如鄉'),nd('里港鄉'),nd('高樹鄉'),nd('鹽埔鄉'),nd('長治鄉'),nd('麟洛鄉'),nd('竹田鄉'),nd('內埔鄉'),nd('萬丹鄉'),nd('潮州鎮'),nd('泰武鄉'),nd('來義鄉'),nd('萬巒鄉'),nd('崁頂鄉'),nd('新埤鄉'),nd('南州鄉'),nd('林邊鄉'),nd('東港鎮'),nd('琉球鄉'),nd('佳冬鄉'),nd('新園鄉'),nd('枋寮鄉'),nd('枋山鄉'),nd('春日鄉'),nd('獅子鄉'),nd('車城鄉'),nd('牡丹鄉'),nd('恆春鎮'),nd('滿州鄉')]),
    nd('臺東縣', [nd('臺東市'),nd('綠島鄉'),nd('蘭嶼鄉'),nd('延平鄉'),nd('卑南鄉'),nd('鹿野鄉'),nd('關山鎮'),nd('海端鄉'),nd('池上鄉'),nd('東河鄉'),nd('成功鎮'),nd('長濱鄉'),nd('太麻里鄉'),nd('金峰鄉'),nd('大武鄉'),nd('達仁鄉')]),
    nd('花蓮縣', [nd('花蓮市'),nd('新城鄉'),nd('秀林鄉'),nd('吉安鄉'),nd('壽豐鄉'),nd('鳳林鎮'),nd('光復鄉'),nd('豐濱鄉'),nd('瑞穗鄉'),nd('萬榮鄉'),nd('玉里鎮'),nd('卓溪鄉'),nd('富里鄉')]),
    nd('宜蘭縣', [nd('宜蘭市'),nd('頭城鎮'),nd('礁溪鄉'),nd('壯圍鄉'),nd('員山鄉'),nd('羅東鎮'),nd('三星鄉'),nd('大同鄉'),nd('五結鄉'),nd('冬山鄉'),nd('蘇澳鎮'),nd('南澳鄉'),nd('釣魚臺')]),
    nd('澎湖縣', [nd('馬公市'),nd('西嶼鄉'),nd('望安鄉'),nd('七美鄉'),nd('白沙鄉'),nd('湖西鄉')]),
    nd('金門縣', [nd('金城鎮'),nd('金湖鎮'),nd('金沙鎮'),nd('金寧鄉'),nd('烈嶼鄉'),nd('烏坵鄉')]),
    nd('連江縣', [nd('南竿鄉'),nd('北竿鄉'),nd('莒光鄉'),nd('東引鄉')]),
  ];
}

function applyTaiwanPreset() {
  if (!model.value) return;
  if (model.value.data.length > 0 && !confirm('套用預設資料將會覆蓋目前的選項，確定繼續？')) return;
  model.value.data = buildTaiwanData();
  const lvls = model.value.levels;
  if (lvls.length === 0) lvls.push({ id: `lvl_${Math.random().toString(36).slice(2, 9)}`, label: '縣市' });
  else lvls[0].label = '縣市';
  if (lvls.length < 2) lvls.push({ id: `lvl_${Math.random().toString(36).slice(2, 9)}`, label: '鄉鎮區' });
  else lvls[1].label = '鄉鎮區';
  while (lvls.length > 2) lvls.pop();
}

function downloadTemplate() {
  if (!store.api) {
    return;
  }

  window.location.href = store.api.cascadeTemplateUrl();
}

async function onFileUpload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file || !model.value) return;

  try {
    const payload = await store.api?.importCascadeData(file);
    if (!payload) return;

    model.value.levels = payload.levels;
    model.value.data = payload.data;
  } catch (error) {
    alert(error instanceof Error ? error.message : '無法解析檔案，請確認格式正確。');
  } finally {
    (event.target as HTMLInputElement).value = '';
  }
}

function apply() {
  if (!model.value) return;
  store.updateQuestion(model.value.elementId, {
    cascade_levels: model.value.levels,
    cascade_data: model.value.data,
  });
  model.value = null;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-settings-overlay sb-theme sb-auto-dark" @click.self="model = null">
      <div class="sb-cascade-dialog">
        <div class="sb-settings-header">
          <h2>編輯巢狀選擇題資料</h2>
          <button class="sb-settings-close" type="button" @click="model = null">✕</button>
        </div>
        <div class="sb-cascade-dialog-body">
          <!-- Levels row -->
          <div class="sb-cascade-dialog-levels">
            <span v-for="(lvl, li) in model.levels" :key="lvl.id" class="sb-cascade-dialog-level-chip">
              <span class="sb-cascade-level-num">{{ li + 1 }}</span>{{ lvl.label }}
              <button
                class="sb-cascade-dialog-level-remove"
                type="button"
                :title="`移除第 ${li + 1} 層`"
                @click="removeLevel(li)"
              >×</button>
            </span>
            <button class="sb-btn-sm" type="button" @click="addLevel" :disabled="model.levels.length >= 5">+ 層級</button>
          </div>

          <!-- Quick preset -->
          <div class="sb-cascade-dialog-preset-bar">
            <span class="sb-cascade-preset-label">快速套用：</span>
            <button class="sb-cascade-preset-btn" type="button" @click="applyTaiwanPreset">
              臺灣縣市鄉鎮區
            </button>
          </div>

          <!-- Upload / Download -->
          <div class="sb-cascade-dialog-toolbar">
            <p class="sb-cascade-dialog-hint">
              XLSX 格式：每列代表一條完整路徑，各欄依序為各層選項（父層相同的行會合併為同一節點）。
            </p>
            <div class="sb-cascade-dialog-btns">
              <button class="sb-btn-sm" type="button" @click="downloadTemplate">下載範例檔</button>
              <label class="sb-btn-sm" style="cursor:pointer">
                上傳資料
                <input type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display:none" @change="onFileUpload" />
              </label>
            </div>
          </div>

          <!-- Tree editor -->
          <div v-if="model.levels.length > 0" class="sb-cascade-tree-wrap">
            <div class="sb-cascade-tree-header">
              <span>第 1 層：{{ model.levels[0]?.label ?? '選項' }}</span>
              <button
                class="sb-btn-sm"
                type="button"
                @click="model!.data.push({ id: `nd_${Math.random().toString(36).slice(2,9)}`, label: '', children: [] })"
              >+ 新增</button>
            </div>
            <div class="sb-cascade-tree-list">
              <div v-for="(node, ni) in model.data" :key="node.id" class="sb-cascade-tree-node depth-0">
                <div class="sb-cascade-tree-row">
                  <span class="sb-cascade-tree-indent"></span>
                  <input class="sb-prop-input" v-model="node.label" :placeholder="`${model.levels[0]?.label ?? '選項'}名稱`" />
                  <button
                    v-if="model.levels.length > 1"
                    class="sb-cascade-tree-act"
                    type="button"
                    :title="`新增第 2 層（${model.levels[1]?.label ?? ''}）`"
                    @click="(node.children = node.children ?? []).push({ id: `nd_${Math.random().toString(36).slice(2,9)}`, label: '', children: [] })"
                  >+ 子項</button>
                  <button class="sb-cascade-tree-act danger" type="button" @click="model!.data.splice(ni, 1)">✕</button>
                </div>
                <!-- Level 2 children -->
                <template v-if="model.levels.length > 1">
                  <div v-for="(child, ci) in (node.children ?? [])" :key="child.id" class="sb-cascade-tree-node depth-1">
                    <div class="sb-cascade-tree-row">
                      <span class="sb-cascade-tree-indent depth-1"></span>
                      <input class="sb-prop-input" v-model="child.label" :placeholder="`${model.levels[1]?.label ?? '選項'}名稱`" />
                      <button
                        v-if="model.levels.length > 2"
                        class="sb-cascade-tree-act"
                        type="button"
                        :title="`新增第 3 層（${model.levels[2]?.label ?? ''}）`"
                        @click="(child.children = child.children ?? []).push({ id: `nd_${Math.random().toString(36).slice(2,9)}`, label: '', children: [] })"
                      >+ 子項</button>
                      <button class="sb-cascade-tree-act danger" type="button" @click="(node.children ?? []).splice(ci, 1)">✕</button>
                    </div>
                    <!-- Level 3 children -->
                    <template v-if="model.levels.length > 2">
                      <div v-for="(gc, gi) in (child.children ?? [])" :key="gc.id" class="sb-cascade-tree-node depth-2">
                        <div class="sb-cascade-tree-row">
                          <span class="sb-cascade-tree-indent depth-2"></span>
                          <input class="sb-prop-input" v-model="gc.label" :placeholder="`${model.levels[2]?.label ?? '選項'}名稱`" />
                          <button class="sb-cascade-tree-act danger" type="button" @click="(child.children ?? []).splice(gi, 1)">✕</button>
                        </div>
                      </div>
                      <div class="sb-cascade-tree-add depth-2">
                        <button class="sb-btn-sm" type="button" @click="(child.children = child.children ?? []).push({ id: `nd_${Math.random().toString(36).slice(2,9)}`, label: '' })">+ {{ model.levels[2]?.label ?? '子項' }}</button>
                      </div>
                    </template>
                  </div>
                  <div class="sb-cascade-tree-add depth-1">
                    <button class="sb-btn-sm" type="button" @click="(node.children = node.children ?? []).push({ id: `nd_${Math.random().toString(36).slice(2,9)}`, label: '', children: [] })">+ {{ model.levels[1]?.label ?? '子項' }}</button>
                  </div>
                </template>
              </div>
            </div>
          </div>
          <div v-else class="sb-cascade-tree-empty">
            請先新增至少一個層級，再編輯巢狀選項資料。
          </div>
        </div>
        <div class="sb-settings-footer">
          <button class="sb-btn" type="button" @click="model = null">取消</button>
          <button class="sb-btn accent" type="button" @click="apply">套用</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

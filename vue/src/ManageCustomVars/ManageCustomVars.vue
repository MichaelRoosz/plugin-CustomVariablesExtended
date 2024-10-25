<!--
  Matomo - free/libre analytics platform
  @link https://matomo.org
  @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class="manageCustomVars">
    <div v-content-intro>
      <h2>
        <EnrichedHeadline help-url="https://matomo.org/docs/custom-variables/">
          {{ translate('CustomVariablesExtended_CustomVariables') }}
        </EnrichedHeadline>
      </h2>
      <p>
        <span v-html="$sanitize(translate(
          'CustomVariablesExtended_ManageDescription', siteName)
        )" />
      </p>
    </div>
    <div
      class="alert alert-info"
      v-show="!isLoading && hasCustomVariablesInGeneral && !hasAtLeastOneUsage"
    >
      {{ translate('CustomVariablesExtended_SlotsReportIsGeneratedOverTime') }}
    </div>
    <div
      v-for="scope in scopes"
      :key="scope.name"
    >
      <ContentBlock :content-title="translate('CustomVariablesExtended_ScopeX', scope.name)">
        <table v-content-table>
          <thead>
            <tr>
              <th>{{ translate('CustomVariablesExtended_Index') }}</th>
              <th>{{ translate('CustomVariablesExtended_Usages') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td
                colspan="3"
                v-show="isLoading"
              >{{ translate('General_Loading') }}</td>
            </tr>
            <tr
              v-for="(customVariables, index) in customVariablesByScope[scope.value]"
              :key="index"
            >
              <td class="index">{{ customVariables.index }}</td>
              <td>
                <span
                  class="unused"
                  v-show="customVariables.usages.length === 0"
                >{{ translate('CustomVariablesExtended_Unused') }}</span>
                <span
                  v-show="customVariables.usages.length"
                  v-for="(cvar, cvarIndex) in sortUsages(customVariables)"
                  :key="cvarIndex"
                >
                  <span :title="translate(
                    'CustomVariablesExtended_UsageDetails',
                    cvar.nb_visits ? cvar.nb_visits : 0,
                    cvar.nb_actions ? cvar.nb_actions : 0,
                  )">{{ cvar.name }}</span>
                  <span v-if="cvarIndex < customVariables.usages.length - 1">, </span>
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </ContentBlock>
    </div>
  </div>
</template>

<script lang="ts">
import { DeepReadonly, defineComponent } from 'vue';
import {
  translate,
  Matomo,
  ContentIntro,
  EnrichedHeadline,
  ContentBlock,
  ContentTable,
  SelectOnFocus,
} from 'CoreHome';
import ManageCustomVarsStore from './ManageCustomVars.store';
import { CustomVariableUsage } from '../types';

interface ManageCustomVarsState {
  siteName: string;
  scopes: { value: string, name: string }[];
}

export default defineComponent({
  components: {
    EnrichedHeadline,
    ContentBlock,
  },
  directives: {
    ContentIntro,
    ContentTable,
    SelectOnFocus,
  },
  data(): ManageCustomVarsState {
    return {
      siteName: Matomo.siteName,
      scopes: [
        {
          value: 'visit',
          name: translate('General_TrackingScopeVisit'),
        },
        {
          value: 'page',
          name: translate('General_TrackingScopePage'),
        },
      ],
    };
  },
  created() {
    ManageCustomVarsStore.init();
  },
  methods: {
    sortUsages(customVar: CustomVariableUsage) {
      const result = [...customVar.usages];
      result.sort((lhs, rhs) => {
        const rhsActions = `${rhs.nb_actions}`;
        const lhsActions = `${lhs.nb_actions}`;
        return parseInt(rhsActions, 10) - parseInt(lhsActions, 10);
      });
      return result;
    },
  },
  computed: {
    isLoading(): boolean {
      return ManageCustomVarsStore.state.value.isLoading;
    },
    hasCustomVariablesInGeneral(): boolean {
      return ManageCustomVarsStore.state.value.hasCustomVariablesInGeneral;
    },
    hasAtLeastOneUsage(): boolean {
      return ManageCustomVarsStore.state.value.hasAtLeastOneUsage;
    },
    numSlotsAvailable():number {
      return ManageCustomVarsStore.state.value.numSlotsAvailable;
    },
    customVariablesByScope(): Record<string, DeepReadonly<CustomVariableUsage>[]> {
      const result: Record<string, DeepReadonly<CustomVariableUsage>[]> = {};
      ManageCustomVarsStore.state.value.customVariables.forEach((customVar) => {
        result[customVar.scope] = result[customVar.scope] || [];
        result[customVar.scope].push(customVar);
      });
      return result;
    },
  },
});
</script>

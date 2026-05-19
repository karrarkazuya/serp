/**@odoo-module **/
import { registry } from "@web/core/registry";
import { Component } from  "@odoo/owl";
const actionRegistry = registry.category("actions");
class DataDashboard extends Component {}
DataDashboard.template = "ss_workflow.dashboard";
//  Tag name that we entered in the first step.
actionRegistry.add("ss_workflow_main_dashboard_tag", DataDashboard);

/**@odoo-module **/
import { registry } from "@web/core/registry";
import { Component } from  "@odoo/owl";
const actionRegistry = registry.category("actions");
class custom_dashboard extends Component {}
custom_dashboard.template = "jtemployees.custom_dashboard";
//  Tag name that we entered in the first step.
actionRegistry.add("jtemployees_main_dashboard_tag", custom_dashboard);

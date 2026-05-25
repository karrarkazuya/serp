/** @odoo-module **/

import { registry } from "@web/core/registry";
import { Component, onWillStart, useState } from "@odoo/owl";

export class TemplateSubBuilder extends Component {
}

TemplateSubBuilder.template = "jtemployees.sub_scheduler_planner_framer";

registry.category("actions").add(
    "jtemployees.sub_scheduler_planner",
    TemplateSubBuilder
);


export class TemplateBuilder extends Component {
}

TemplateBuilder.template = "jtemployees.scheduler_planner_framer";

registry.category("actions").add(
    "jtemployees.scheduler_planner",
    TemplateBuilder
);
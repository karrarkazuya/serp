/** @odoo-module **/

import { registry } from "@web/core/registry";
import { Component, onWillStart, useState } from "@odoo/owl";
import { useService } from "@web/core/utils/hooks";
import { download } from "@web/core/network/download";

export class WorkflowTaskPerformanceReportClient extends Component {
    setup() {
        this.orm = useService("orm");
        this.action = useService("action");

        this.state = useState({
            report_name: "",
            parameters: {},
            data: [],
            date_from: null,
            date_to: null,
        });

        onWillStart(() => this.load());
    }

    async load() {
        const res = await this.orm.call(
            "ss.report.workflow_task_performance",
            "get_report",
            [[]],
            {
                date_from: this.state.date_from,
                date_to: this.state.date_to,
            }
        );
        Object.assign(this.state, res);
        this.state.date_from = (res.parameters && res.parameters.date_from) || null;
        this.state.date_to = (res.parameters && res.parameters.date_to) || null;
    }

    applyFilters() {
        this.load();
    }

    async toggleTemplate(ev) {
        const taskTemplateId = Number(ev.currentTarget.dataset.taskTemplateId);
        const item = this.state.data.find(
            row => row.task_template_id === taskTemplateId
        );

        if (!item || item.lines_loaded || !ev.currentTarget.open) {
            return;
        }

        const lines = await this.orm.call(
            "ss.report.workflow_task_performance",
            "get_template_lines",
            [[]],
            {
                task_template_id: taskTemplateId,
                date_from: this.state.parameters.date_from,
                date_to: this.state.parameters.date_to,
            }
        );

        item.lines = lines || [];
        item.lines_loaded = true;
    }

    openTask(ev) {
        const taskId = Number(ev.currentTarget.dataset.taskId);
        if (!taskId) {
            return;
        }

        this.action.doAction({
            type: "ir.actions.act_window",
            res_model: "ssw.proc.tasks",
            res_id: taskId,
            views: [[false, "form"]],
            target: "current",
        });
    }

    async print_pdf() {
        const params = new URLSearchParams({
            date_from: this.state.date_from || "",
            date_to: this.state.date_to || "",
        });

        download({
            url: `/ss_workflow_reports/task_performance/pdf?${params.toString()}`,
            data: {},
        });
    }

    print_xlsx() {
        const params = new URLSearchParams({
            date_from: this.state.date_from || "",
            date_to: this.state.date_to || "",
        });
        window.open(`/ss_workflow_reports/task_performance/xlsx?${params.toString()}`, "_blank");
    }
}

WorkflowTaskPerformanceReportClient.template = "ss_workflow.task_performance_report";

registry.category("actions").add(
    "ss_workflow.task_performance_report",
    WorkflowTaskPerformanceReportClient
);

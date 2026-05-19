/** @odoo-module **/

import { registry } from "@web/core/registry";
import { Component, onWillStart, useState } from "@odoo/owl";
import { useService } from "@web/core/utils/hooks";
import { download } from "@web/core/network/download";

export class WorkflowProcedurePerformanceReportClient extends Component {
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
            "ss.report.workflow_procedure_performance",
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
        const templateId = Number(ev.currentTarget.dataset.templateId);
        const item = this.state.data.find(
            row => row.template_id === templateId
        );

        if (!item || item.lines_loaded || !ev.currentTarget.open) {
            return;
        }

        const lines = await this.orm.call(
            "ss.report.workflow_procedure_performance",
            "get_template_lines",
            [[]],
            {
                template_id: templateId,
                date_from: this.state.parameters.date_from,
                date_to: this.state.parameters.date_to,
            }
        );

        item.lines = lines || [];
        item.lines_loaded = true;
    }

    openProcedure(ev) {
        const procedureId = Number(ev.currentTarget.dataset.procedureId);
        if (!procedureId) {
            return;
        }

        this.action.doAction({
            type: "ir.actions.act_window",
            res_model: "ssw.procedures",
            res_id: procedureId,
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
            url: `/ss_workflow_reports/procedure_performance/pdf?${params.toString()}`,
            data: {},
        });
    }

    print_xlsx() {
        const params = new URLSearchParams({
            date_from: this.state.date_from || "",
            date_to: this.state.date_to || "",
        });
        window.open(`/ss_workflow_reports/procedure_performance/xlsx?${params.toString()}`, "_blank");
    }
}

WorkflowProcedurePerformanceReportClient.template = "ss_workflow.procedure_performance_report";

registry.category("actions").add(
    "ss_workflow.procedure_performance_report",
    WorkflowProcedurePerformanceReportClient
);

/** @odoo-module **/

import { registry } from "@web/core/registry";
import { Component, onWillStart, useState } from "@odoo/owl";
import { useService } from "@web/core/utils/hooks";
import { download } from "@web/core/network/download";

export class PayrollReportClient extends Component {
    setup() {
        this.orm    = useService("orm");
        this.action = useService("action");

        this.state = useState({
            report_name: "",
            parameters:  {},
            data:        [],
            totals:      {},

            payroll_id:   null,
            payroll_name: "",

            employees:      [],
            employees_data: [],
        });

        onWillStart(() => this.load());
    }

    /* =====================
       LOAD TOTALS
    ===================== */
    async load() {
        const res = await this.orm.call(
            "jtemployees.report.payroll",
            "get_report_totals",
            [[]],
            {
                payroll_id: this.state.payroll_id,
                employees:  this.state.employees,
            }
        );
        Object.assign(this.state, res);
    }

    /* =====================
       LAZY LOAD EMPLOYEE LINES
    ===================== */
    async toggleEmployee(ev) {
        const employeeId = Number(ev.currentTarget.dataset.employeeId);
        const emp = this.state.data.find(e => e.employee_id === employeeId);

        if (!emp || emp.lines_loaded) return;

        const lines = await this.orm.call(
            "jtemployees.report.payroll",
            "get_employee_lines",
            [[]],
            {
                employee_id: employeeId,
                payroll_id:  this.state.payroll_id,
            }
        );

        emp.lines        = lines || [];
        emp.lines_loaded = true;
    }

    /* =====================
       FILTER ACTIONS
    ===================== */
    applyFilters() {
        this.load();
    }

    /* =====================
       PARTNER SELECTOR (WIZARD)
    ===================== */
    openPayrollSelector() {
        this.action.doAction(
            {
                type:      "ir.actions.act_window",
                name:      "Select Payroll",
                res_model: "jtemployees.payroll.select.wizard",
                views:     [[false, "form"]],
                target:    "new",
            },
            {
                onClose: (info) => {
                    if (!info?.payroll_id) return;
                    this.state.payroll_id   = info.payroll_id;
                    this.state.payroll_name = info.payroll_name || "";
                },
            }
        );
    }

    clearPartner() {
        this.state.payroll_id   = null;
        this.state.payroll_name = "";
    }

    /* =====================
       EMPLOYEE SELECTOR (WIZARD)
    ===================== */
    openEmployeeSelector() {
        this.action.doAction(
            {
                type:      "ir.actions.act_window",
                name:      "Select Employees",
                res_model: "jtemployees.employee.select.wizard",
                views:     [[false, "form"]],
                target:    "new",
            },
            {
                onClose: async (info) => {
                    const ids = info?.selected_employee_ids || [];
                    if (!ids.length) return;

                    for (const id of ids) {
                        if (!this.state.employees.includes(id)) {
                            this.state.employees.push(id);
                        }
                    }

                    const records = await this.orm.read("hr.employee", ids, ["name"]);
                    for (const rec of records) {
                        if (!this.state.employees_data.some(e => e.id === rec.id)) {
                            this.state.employees_data.push({ id: rec.id, title: rec.name });
                        }
                    }
                },
            }
        );
    }

    removeEmployee(ev) {
        const id = Number(ev.currentTarget.dataset.id);
        this.state.employees      = this.state.employees.filter(e => e !== id);
        this.state.employees_data = this.state.employees_data.filter(e => e.id !== id);
    }

    /* =====================
       EXPORTS
    ===================== */
    async print_pdf() {
        const params = new URLSearchParams({
            payroll_id: this.state.payroll_id || "",
            employees:  this.state.employees.join(","),
        });
        download({ url: `/jtemployees_reports/payroll/pdf?${params.toString()}`, data: {} });
    }

    print_xlsx() {
        const params = new URLSearchParams({
            payroll_id: this.state.payroll_id || "",
            employees:  this.state.employees.join(","),
        });
        window.open(`/jtemployees_reports/payroll/xlsx?${params.toString()}`, "_blank");
    }
}

PayrollReportClient.template = "jtemployees.payroll_report";

registry.category("actions").add("jtemployees.payroll_report", PayrollReportClient);

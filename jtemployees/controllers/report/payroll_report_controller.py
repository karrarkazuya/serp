from odoo import http
from odoo.http import request
from odoo.tools.misc import get_lang
import io
import xlsxwriter


class PayrollReportController(http.Controller):

    # ------------------------------------------------------------------
    # PDF
    # ------------------------------------------------------------------
    @http.route('/jtemployees_reports/payroll/pdf', type='http', auth='user')
    def download_payroll_pdf(self, payroll_id=None, employees=None, **kw):
        if employees:
            employees = list(map(int, str(employees).split(',')))
        else:
            employees = False
            

        report = request.env['jtemployees.report.payroll'].get_report_export(
            payroll_id= payroll_id or False,
            employees=employees,
        )

        html = request.env['ir.ui.view']._render_template(
            'jtemployees.payroll_report_pdf_manual',
            {
                'report_name': report['report_name'],
                'parameters':  report['parameters'],
                'data':        report['data'],
                'totals':      report['totals'],
                'lang':        get_lang(request.env).code,
            }
        )

        pdf = request.env['ir.actions.report']._run_wkhtmltopdf(
            [html],
            specific_paperformat_args={
                'orientation':    'Landscape',
                'margin_top':     15,
                'margin_bottom':  15,
                'margin_left':    10,
                'margin_right':   10,
            }
        )

        return request.make_response(
            pdf,
            headers=[
                ('Content-Type',        'application/pdf'),
                ('Content-Disposition', 'attachment; filename=payroll_report.pdf'),
            ],
        )

    # ------------------------------------------------------------------
    # XLSX
    # ------------------------------------------------------------------
    @http.route('/jtemployees_reports/payroll/xlsx', type='http', auth='user')
    def download_payroll_xlsx(self, payroll_id=None, employees=None, **kw):
        if employees:
            employees = list(map(int, str(employees).split(',')))
        else:
            employees = False

        report = request.env['jtemployees.report.payroll'].get_report_export(
            payroll_id=payroll_id or False,
            employees=employees,
        )

        output   = io.BytesIO()
        workbook = xlsxwriter.Workbook(output, {'in_memory': True})
        sheet    = workbook.add_worksheet('Payroll Report')

        fmt_header     = workbook.add_format({'bold': True,   'border': 1, 'align': 'center', 'bg_color': '#374151', 'font_color': '#ffffff'})
        fmt_cell       = workbook.add_format({'border': 1})
        fmt_amt        = workbook.add_format({'border': 1, 'align': 'right', 'num_format': '#,##0.00'})
        fmt_section    = workbook.add_format({'bold': True,   'border': 1, 'bg_color': '#e5e7eb'})
        fmt_sub_header = workbook.add_format({'italic': True, 'border': 1, 'bg_color': '#f3f4f6', 'font_size': 10})
        fmt_sub_cell   = workbook.add_format({'border': 1, 'font_size': 10, 'font_color': '#374151'})
        fmt_total      = workbook.add_format({'bold': True,   'border': 1, 'bg_color': '#d1fae5', 'align': 'right', 'num_format': '#,##0.00'})

        # Column widths: #, Name, Basic, Alloc, Bounces, Shortage, Absence, Hrs, OT Hrs, OT Amt, Total, Leave
        widths = [5, 28, 14, 14, 16, 12, 14, 14, 14, 14, 14, 10]
        for i, w in enumerate(widths):
            sheet.set_column(i, i, w)

        row = 0
        sheet.merge_range(row, 0, row, 10, report['report_name'], fmt_header)
        row += 1
        sheet.merge_range(row, 0, row, 10,
            f"{report['parameters']['payroll_name']}",
            workbook.add_format({'border': 1, 'align': 'center'}))
        row += 2

        col_headers = [
            '#', 'Employee Name', 'Basic Salary', 'Allocations', 'Bounces/Deductions',
            'Shortage', 'Absence', 'Working Hours', 'Overtime Hours', 'Overtime Amount',
            'Total Salary', 'Leave Days',
        ]
        sheet.write_row(row, 0, col_headers, fmt_header)
        row += 1

        for idx, emp in enumerate(report['data'], 1):
            sheet.write(row, 0, idx, fmt_cell)
            sheet.write(row, 1, emp['employee_name'], fmt_section)
            sheet.write_number(row, 2,  emp['basic_salary_num'],    fmt_amt)
            sheet.write_number(row, 3,  emp['allocations_num'],     fmt_amt)
            sheet.write_number(row, 4,  emp['bounces_num'],         fmt_amt)
            sheet.write_number(row, 5,  emp['deductions_num'],      fmt_amt)
            sheet.write_number(row, 6,  emp['shortage_num'],        fmt_amt)
            sheet.write_number(row, 7,  emp['absence_num'],         fmt_amt)
            sheet.write_number(row, 8,  emp['total_hours_num'],     fmt_amt)
            sheet.write_number(row, 9,  emp['overtime_hours_num'],  fmt_amt)
            sheet.write_number(row, 10,  emp['overtime_amount_num'], fmt_amt)
            sheet.write_number(row, 11,  emp['total_salary_num'],   fmt_amt)
            sheet.write(row, 12, emp['leave_days'], fmt_cell)
            row += 1

            if emp.get('lines'):
                day_cols = ['', 'Type', 'Title', 'Date', 'Hours', 'Amount']
                for c, h in enumerate(day_cols):
                    sheet.write(row, c, h, fmt_sub_header)
                row += 1
                for line in emp['lines']:
                    sheet.write(row, 0, '', fmt_sub_cell)
                    sheet.write(row, 1, line['detail'],               fmt_sub_cell)
                    sheet.write(row, 2, line['sub'],                  fmt_sub_cell)
                    sheet.write(row, 3, line['date'],                 fmt_sub_cell)
                    sheet.write(row, 4, line['hours'],                fmt_sub_cell)
                    sheet.write(row, 5, line['amount'],               fmt_sub_cell)
                    row += 1

        totals = report.get('totals', {})
        sheet.merge_range(row, 0, row, 1, 'GRAND TOTAL', fmt_section)
        sheet.write(row, 2,  totals.get('basic_salary',    ''), fmt_total)
        sheet.write(row, 3,  totals.get('allocations',     ''), fmt_total)
        sheet.write(row, 4,  totals.get('bounces',         ''), fmt_total)
        sheet.write(row, 5,  totals.get('deductions',      ''), fmt_total)
        sheet.write(row, 6,  totals.get('shortage',        ''), fmt_total)
        sheet.write(row, 7,  totals.get('absence',         ''), fmt_total)
        sheet.write(row, 8,  '', fmt_total)
        sheet.write(row, 9,  '', fmt_total)
        sheet.write(row, 10,  totals.get('overtime_amount', ''), fmt_total)
        sheet.write(row, 11,  totals.get('total_salary',   ''), fmt_total)
        sheet.write(row, 12, '', fmt_total)

        workbook.close()
        output.seek(0)

        return request.make_response(
            output.read(),
            headers=[
                ('Content-Type',        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ('Content-Disposition', 'attachment; filename=payroll_report.xlsx'),
            ],
        )

    def get_company(self, request):
        try:
            cids = request.httprequest.cookies.get('cids')
            if cids:
                company_ids = [int(cid) for cid in cids.split(',')]
            else:
                company_ids = [request.env.user.company_id.id]
            env = request.env(context=dict(
                request.env.context,
                allowed_company_ids=company_ids,
            ))
            return env.company.id
        except:
            return 0

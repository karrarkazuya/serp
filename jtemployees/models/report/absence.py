from odoo import models, fields, api
from datetime import datetime, timedelta, time

class jt_employee_absence(models.Model):
    _name = 'jtemployees.report.absence'
    _description = 'Employee Absence'
    _order = 'id desc'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    employee_id = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    date = fields.Date(string='Date', required=True, tracking=True)
    has_leave_request = fields.Boolean(string='Has leave request', compute='_compute_has_leave_request')
    
    leave_request_id = fields.Many2one('jtemployees.requests', string='Leave request')
    department_id = fields.Many2one('hr.department', string='Department')
    
    
    def _compute_has_leave_request(self):
        for item in self:
            has_leave_request = self.env['jtemployees.requests'].sudo().search([
                ('employee_id', '=', item.employee_id.id),
                ('date_from', '>=', item.date),
                ('date_to', '>=', item.date),
                ('deleted', '=', False),
                ('hr_approved', '!=', 'rejected')
            ], limit=1)
            
            if has_leave_request:
                self.sudo().write({
                    "leave_request_id": has_leave_request.id
                })
            item.has_leave_request = bool(has_leave_request)

    def sync_data(self):
        date_check_period = fields.Date.today() - timedelta(days=30)
        date_skip = fields.Date.today()

        days = self.env['jtemployees.passed.days'].sudo().search([
            ('is_day_off', '=', False),
            ('date', '>', date_check_period),
            ('date', '<', date_skip),
        ])

        for day in days:
            if not day.employee_id:
                continue

            start_dt = datetime.combine(day.date, time.min)
            end_dt = datetime.combine(day.date, time.max)

            fingerprint_at_this_date = self.env['jtemployees.fd.log'].sudo().search([
                ('employee_id', '=', day.employee_id.id),
                ('check_in', '>=', start_dt),
                ('check_in', '<=', end_dt),
            ], limit=1)

            if not fingerprint_at_this_date:
                added_already = self.env['jtemployees.report.absence'].sudo().search(
                    [
                        ('employee_id', '=', day.employee_id.id),
                        ('date', '=', day.date)
                    ], limit=1
                )
                if not added_already:
                    self.env['jtemployees.report.absence'].sudo().create({
                        "employee_id": day.employee_id.id,
                        "department_id": day.employee_id.department_id.id,
                        "date": day.date
                    })
    


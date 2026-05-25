from odoo import models, fields, api

class payrolls_details(models.Model):
    _name = 'jtemployees.payrolls.dsubs'
    _description = 'Payroll details subs'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    
    details_id            = fields.Many2one('jtemployees.payrolls.details', string='Details ID', tracking=True)
    name                  = fields.Char(string='Name', required=True, tracking=True)
    date                  = fields.Datetime(string='Date', required=True, tracking=True)
    hours                 = fields.Float(string='Hours', required=True, tracking=True)
    hours_readable        = fields.Char(string='Hours', compute='_compute_readable_period')
    amount                = fields.Float(string='Amount', required=True, tracking=True)
    employee              = fields.Many2one('hr.employee', string='Employee', store=True, compute='_compute_employee', tracking=True)
    
    deleted               = fields.Boolean(string='Active', default=False, tracking=True)
    
    def _compute_readable_period(self):
        for record in self:
            hours_float = record.hours  # e.g. 8.50

            hours = int(hours_float)
            minutes = int(round((hours_float - hours) * 60))

            # Handle edge case where rounding gives 60 minutes
            if minutes == 60:
                hours += 1
                minutes = 0

            record.hours_readable = f"{hours:02d}:{minutes:02d}"
            
    @api.depends("date")
    def _compute_employee(self):
        for record in self:
            record.employee = record.details_id.payroll_slip.employee.id
            
    def unlink(self):
        for record in self:
            record.sudo().write({'deleted': True})
        return True
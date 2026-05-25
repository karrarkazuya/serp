from odoo import models, fields, api

class payrolls_slips_warnings(models.Model):
    _name = 'jtemployees.payrolls.swarning'
    _description = 'Payroll slip warnings'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Text(
        string='Title',
        tracking=True
    )
    
    payroll_slip       = fields.Many2one('jtemployees.payrolls.slips', string='Payroll Slip', required=True, tracking=True)
    date               = fields.Date(string='Date', required=True, tracking=True)
    shortage_hours     = fields.Float(string='Shortages (minutes)', required=True, tracking=True)
    employee           = fields.Many2one('hr.employee', string='Employee', store=True, compute='_compute_employee', tracking=True)
    
    deleted            = fields.Boolean(string='Active', default=False, tracking=True)
    
    @api.depends("date")
    def _compute_employee(self):
        for record in self:
            record.employee = record.payroll_slip.employee.id
            
    def unlink(self):
        for record in self:
            record.sudo().write({'deleted': True})
        return True

 
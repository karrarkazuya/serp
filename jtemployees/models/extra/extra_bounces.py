from odoo import models, fields, exceptions

class extra_bounces(models.Model):
    _name = 'jtemployees.bounces'
    _description = 'Bounces and Deductions for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = 'create_date desc'
    _check_company_auto = True
    
    name = fields.Text(
        string='Title',
        tracking=True
    )
    
    employee_id = fields.Many2one('hr.employee', string='Employee', tracking=True)
    employee_ids = fields.Many2many('hr.employee', string='Employees', required=True, tracking=True)
    company_id = fields.Many2one('res.company', string='Company')
    date = fields.Date(string='Date', required=True, tracking=True)
    deleted = fields.Date(string='Deleted', default=False, tracking=True)
    
    amount = fields.Float(
        string='Amount',
        required=True,
        tracking=True
    )
    
    def create(self, vals_list):
        company = False
        
        if 'employee_id' in vals_list:
            vals_list['employee_ids'] = [[4, vals_list['employee_id']]]
            
        if 'employee_ids' in vals_list:
            for item in vals_list['employee_ids']:
                hr = self.env['hr.employee'].search([('id', '=', item[1])], limit=1)
                if not company:
                    company = hr.company_id.id
                if company != hr.company_id.id:
                    raise exceptions.ValidationError("Different company users, can not add")
                
        vals_list['company_id'] = company
            
        return super().create(vals_list)
    
    def write(self, values):
        if 'employee_ids' in values:
            raise exceptions.ValidationError("You can not modify the employees list")
        return super().write(values)
    
